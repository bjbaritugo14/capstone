begin;

-- Expose a small authenticated RPC API, never the Laravel account/session tables.
create schema if not exists mobile_private;
revoke all on schema mobile_private from public;
create table if not exists mobile_private.accounts (
  auth_id uuid primary key references auth.users(id) on delete cascade,
  user_id bigint unique not null references public.users(user_id) on delete cascade
);

create or replace function mobile_private.provision_officer() returns trigger
language plpgsql security definer set search_path = '' as $$
declare aid uuid;
begin
  if pg_trigger_depth() > 1 then return new; end if;
  select auth_id into aid from mobile_private.accounts where user_id = new.user_id;
  if aid is null and exists(select 1 from public.roles where role_id=new.role_id and role_name='field_officer') then
    if new.password !~ '^\$2[aby]\$' then
      raise exception 'Field Officer password must use bcrypt before Supabase migration';
    end if;
    aid := gen_random_uuid();
    insert into auth.users(instance_id,id,aud,role,email,encrypted_password,email_confirmed_at,
      confirmation_token,recovery_token,email_change_token_new,email_change,
      raw_app_meta_data,raw_user_meta_data,created_at,updated_at)
    values('00000000-0000-0000-0000-000000000000',aid,'authenticated','authenticated',lower(new.email),
      replace(new.password,'$2y$','$2b$'),now(),'','','','',
      '{"provider":"email","providers":["email"]}',jsonb_build_object('full_name',new.full_name),now(),now());
    insert into auth.identities(provider_id,user_id,identity_data,provider,created_at,updated_at)
    values(aid::text,aid,jsonb_build_object('sub',aid::text,'email',lower(new.email),'email_verified',true),'email',now(),now());
    insert into mobile_private.accounts(auth_id,user_id) values(aid,new.user_id);
  elsif aid is not null then
    update auth.users set email=lower(new.email),encrypted_password=replace(new.password,'$2y$','$2b$'),
      raw_user_meta_data=jsonb_build_object('full_name',new.full_name),updated_at=now() where id=aid;
    update auth.identities set identity_data=identity_data || jsonb_build_object('email',lower(new.email)),updated_at=now()
      where user_id=aid and provider='email';
  end if;
  return new;
end $$;
drop trigger if exists mobile_provision_officer on public.users;
create trigger mobile_provision_officer after insert or update of email,password,role_id,full_name on public.users
  for each row execute function mobile_private.provision_officer();

-- A password changed in the mobile app must also work in the Laravel website.
create or replace function mobile_private.sync_password() returns trigger
language plpgsql security definer set search_path = '' as $$
begin
  if pg_trigger_depth() = 1 and new.encrypted_password is distinct from old.encrypted_password then
    update public.users set password=replace(new.encrypted_password,'$2b$','$2y$')
    where user_id=(select user_id from mobile_private.accounts where auth_id=new.id);
  end if;
  return new;
end $$;
drop trigger if exists mobile_sync_password on auth.users;
create trigger mobile_sync_password after update of encrypted_password on auth.users
  for each row execute function mobile_private.sync_password();

-- Provision existing officers without changing their passwords.
update public.users u set full_name=u.full_name where exists
  (select 1 from public.roles r where r.role_id=u.role_id and r.role_name='field_officer')
  and not exists(select 1 from mobile_private.accounts a where a.user_id=u.user_id);

create or replace function mobile_private.officer_id() returns bigint
language plpgsql stable security definer set search_path = '' as $$
declare uid bigint;
begin
  select u.user_id into uid from mobile_private.accounts a join public.users u using(user_id)
    join public.roles r using(role_id)
    where a.auth_id=auth.uid() and u.status='active' and r.role_name='field_officer';
  if uid is null then raise exception 'An active Field Officer account is required.' using errcode='42501'; end if;
  return uid;
end $$;

create or replace function mobile_private.report_json(rid bigint) returns jsonb
language sql stable security definer set search_path = '' as $$
select jsonb_build_object(
  'id',r.report_id,'barangay',b.barangay_name,'purok',coalesce(l.sitio_purok,''),
  'description',coalesce(r.description,''),'disasterType',r.disaster_type,'severity',r.damage_severity,
  'affectedStructures',r.affected_structures,'latitude',l.latitude::text,'longitude',l.longitude::text,
  'reportDate',r.incident_datetime::date::text,'status',r.status,'createdAt',r.created_at,
  'validationRemarks',case when r.status='pending' then '' else coalesce(v.remarks,'') end,
  'validatedAt',case when r.status='pending' then '' else coalesce(v.validated_at::text,'') end,
  'validatedBy',case when r.status='pending' then '' else coalesce(v.full_name,'') end,
  'photos',coalesce((select jsonb_agg(i.image_path order by i.image_id) from public.report_images i
    where i.report_id=r.report_id and i.family_id is null),'[]'::jsonb),
  'families',coalesce((select jsonb_agg(jsonb_build_object(
    'firstName',coalesce(f.first_name,split_part(f.family_head_name,' ',1)),
    'lastName',coalesce(f.last_name,substring(f.family_head_name from position(' ' in f.family_head_name)+1)),
    'familyHeadName',f.family_head_name,'householdMembers',f.household_members,
    'contactNumber',coalesce(f.contact_number,''),'evacuationStatus',coalesce(f.evacuation_status,''),
    'description',coalesce(f.description,''),'severity',coalesce(f.damage_severity,r.damage_severity),
    'latitude',coalesce(f.latitude::text,''),'longitude',coalesce(f.longitude::text,''),
    'photos',coalesce((select jsonb_agg(i.image_path order by i.image_id) from public.report_images i where i.family_id=f.family_id),'[]'::jsonb)
  ) order by f.family_id) from public.affected_families f where f.report_id=r.report_id),'[]'::jsonb))
from public.disaster_reports r join public.incident_locations l using(location_id) join public.barangays b using(barangay_id)
left join lateral(select rv.remarks,rv.validated_at,u.full_name from public.report_validations rv
  join public.users u on u.user_id=rv.validated_by where rv.report_id=r.report_id order by rv.validated_at desc,rv.validation_id desc limit 1) v on true
where r.report_id=rid;
$$;

create or replace function mobile_private.accident_json(aid bigint) returns jsonb
language sql stable security definer set search_path = '' as $$
select jsonb_build_object(
  'id',a.accident_id,'barangay',b.barangay_name,'purok',coalesce(l.sitio_purok,''),'roadSegment',coalesce(l.road_segment,''),
  'description',coalesce(a.description,''),'accidentType',a.accident_type,'vehicleType',coalesce(a.vehicle_type,''),
  'personFirstName',coalesce(a.involved_person_first_name,''),'personLastName',coalesce(a.involved_person_last_name,''),
  'personName',coalesce(a.involved_person_name,''),'vehiclesInvolved',a.vehicles_involved,
  'injuredCount',a.injured_count,'fatalityCount',a.fatality_count,'latitude',l.latitude::text,'longitude',l.longitude::text,
  'incidentDate',a.incident_datetime::date::text,'status',a.status,'createdAt',a.created_at,
  'validationRemarks',case when a.status='recorded' then '' else coalesce(v.remarks,'') end,
  'validatedAt',case when a.status='recorded' then '' else coalesce(v.validated_at::text,'') end,
  'validatedBy',case when a.status='recorded' then '' else coalesce(v.full_name,'') end,
  'photos',coalesce((select jsonb_agg(i.image_path order by i.image_id) from public.accident_images i where i.accident_id=a.accident_id),'[]'::jsonb),
  'involvedPersons',coalesce((select jsonb_agg(jsonb_build_object('firstName',coalesce(p.first_name,''),'lastName',coalesce(p.last_name,''),
    'personName',p.person_name,'role',coalesce(p.role,''),'contactNumber',coalesce(p.contact_number,'')) order by p.person_id)
    from public.accident_involved_persons p where p.accident_id=a.accident_id),'[]'::jsonb))
from public.vehicular_accidents a join public.incident_locations l using(location_id) join public.barangays b using(barangay_id)
left join lateral(select av.remarks,av.validated_at,u.full_name from public.accident_validations av
  join public.users u on u.user_id=av.validated_by where av.accident_id=a.accident_id order by av.validated_at desc,av.validation_id desc limit 1) v on true
where a.accident_id=aid;
$$;

create or replace function mobile_private.check_photos(photos jsonb, existing_paths text[]) returns void
language plpgsql stable security definer set search_path = '' as $$
declare photo text; object_name text;
begin
  if jsonb_typeof(photos) <> 'array' or jsonb_array_length(photos)>30 then raise exception 'Invalid photos.'; end if;
  for photo in select jsonb_array_elements_text(photos) loop
    if photo=any(existing_paths) then continue; end if;
    object_name := substring(photo from length('supabase://mobile-evidence/')+1);
    if photo not like 'supabase://mobile-evidence/' || auth.uid()::text || '/%'
      or not exists(select 1 from storage.objects where bucket_id='mobile-evidence' and name=object_name) then
      raise exception 'Photo does not belong to this account.' using errcode='42501';
    end if;
  end loop;
end $$;

-- One atomic transaction saves a report, location, family/person records, and photo references.
create or replace function public.mobile_api(action text, payload jsonb default '{}'::jsonb, record_id bigint default null)
returns jsonb language plpgsql security definer set search_path = '' as $$
declare uid bigint := mobile_private.officer_id(); bid bigint; lid bigint; rid bigint := record_id;
  fid bigint; lat numeric; lng numeric; family jsonb; person jsonb; photo text;
  old_paths text[] := '{}'; old_status text; is_report boolean := action like '%report%'; result jsonb;
begin
  if action='profile' then
    return (select jsonb_build_object('id',user_id,'name',full_name,'email',email,'role','field_officer') from public.users where user_id=uid);
  elsif action='barangays' then
    return (select coalesce(jsonb_agg(jsonb_build_object('id',barangay_id,'name',barangay_name,'municipality',municipality,'province',province) order by barangay_name),'[]'::jsonb) from public.barangays where status='active');
  elsif action='reports' then
    return (select coalesce(jsonb_agg(mobile_private.report_json(report_id) order by created_at desc,report_id desc),'[]'::jsonb) from public.disaster_reports where user_id=uid and archived_at is null);
  elsif action='accidents' then
    return (select coalesce(jsonb_agg(mobile_private.accident_json(accident_id) order by created_at desc,accident_id desc),'[]'::jsonb) from public.vehicular_accidents where user_id=uid and archived_at is null);
  end if;
  if action not in ('save_report','save_accident','delete_report','delete_accident') then raise exception 'Unknown mobile action.'; end if;
  if rid is not null then
    if is_report then
      select location_id,status into lid,old_status from public.disaster_reports where report_id=rid and user_id=uid and archived_at is null for update;
      select coalesce(array_agg(image_path),'{}') into old_paths from public.report_images where report_id=rid;
    else
      select location_id,status into lid,old_status from public.vehicular_accidents where accident_id=rid and user_id=uid and archived_at is null for update;
      select coalesce(array_agg(image_path),'{}') into old_paths from public.accident_images where accident_id=rid;
    end if;
    if lid is null then raise exception 'Record not found or access denied.' using errcode='42501'; end if;
  end if;
  if action like 'delete_%' then
    if rid is null then raise exception 'Record ID required.'; end if;
    if is_report then
      delete from public.report_images where report_id=rid;
      delete from public.affected_families where report_id=rid;
      delete from public.disaster_reports where report_id=rid;
    else
      delete from public.accident_images where accident_id=rid;
      delete from public.accident_involved_persons where accident_id=rid;
      delete from public.vehicular_accidents where accident_id=rid;
    end if;
    return '{"deleted":true}'::jsonb;
  end if;
  select barangay_id into bid from public.barangays where barangay_name=trim(payload->>'barangay') and status='active';
  if bid is null then raise exception 'Select an active barangay.'; end if;
  lat := nullif(payload->>'latitude','')::numeric; lng := nullif(payload->>'longitude','')::numeric;
  if is_report and (lat is null or lng is null or (lat=0 and lng=0)) then
    for family in select value from jsonb_array_elements(coalesce(payload->'families','[]')) loop
      lat := nullif(family->>'latitude','')::numeric; lng := nullif(family->>'longitude','')::numeric;
      exit when lat is not null and lng is not null and not(lat=0 and lng=0);
    end loop;
  end if;
  if lat is null or lng is null or lat not between -90 and 90 or lng not between -180 and 180 or (lat=0 and lng=0) then
    raise exception 'Capture a valid GPS location before submitting.';
  end if;
  perform mobile_private.check_photos(coalesce(payload->'photos','[]'),old_paths);
  if is_report then
    if coalesce(payload->>'disasterType','') not in ('Flood','Typhoon','Landslide','Earthquake','Fire','Other')
      or coalesce(payload->>'severity','') not in ('minor','moderate','severe')
      or coalesce((payload->>'affectedStructures')::integer,0) not between 0 and 100000
      or nullif(payload->>'reportDate','')::date is null then raise exception 'Invalid report details.'; end if;
    for family in select value from jsonb_array_elements(coalesce(payload->'families','[]')) loop
      if nullif(trim(family->>'firstName'),'') is null or nullif(trim(family->>'lastName'),'') is null
        or coalesce((family->>'householdMembers')::integer,-1) < 0
        or coalesce(nullif(family->>'severity',''),payload->>'severity') not in ('minor','moderate','severe')
        or nullif(family->>'latitude','')::numeric not between -90 and 90
        or nullif(family->>'longitude','')::numeric not between -180 and 180 then raise exception 'Invalid affected family details.'; end if;
      perform mobile_private.check_photos(coalesce(family->'photos','[]'),old_paths);
    end loop;
  else
    if nullif(trim(payload->>'accidentType'),'') is null or nullif(trim(payload->>'description'),'') is null
      or coalesce((payload->>'vehiclesInvolved')::integer,1)<1
      or coalesce((payload->>'injuredCount')::integer,0)<0 or coalesce((payload->>'fatalityCount')::integer,0)<0
      or nullif(payload->>'incidentDate','')::date is null then raise exception 'Invalid accident details.'; end if;
    for person in select value from jsonb_array_elements(coalesce(payload->'involvedPersons','[]')) loop
      if nullif(trim(person->>'firstName'),'') is null or nullif(trim(person->>'lastName'),'') is null then raise exception 'Enter first and last names for every involved person.'; end if;
    end loop;
  end if;
  -- New locations on edits avoid modifying another record that shares an old location.
  insert into public.incident_locations(barangay_id,latitude,longitude,sitio_purok,road_segment)
    values(bid,lat,lng,nullif(payload->>'purok',''),nullif(payload->>'roadSegment','')) returning location_id into lid;
  if is_report then
    if rid is null then
      insert into public.disaster_reports(user_id,location_id,disaster_type,description,damage_severity,affected_families,affected_structures,incident_datetime,status)
      values(uid,lid,payload->>'disasterType',payload->>'description',payload->>'severity',jsonb_array_length(coalesce(payload->'families','[]')),
        coalesce((payload->>'affectedStructures')::integer,0),(payload->>'reportDate')::date,'pending') returning report_id into rid;
    else
      update public.disaster_reports set location_id=lid,disaster_type=payload->>'disasterType',description=payload->>'description',
        damage_severity=payload->>'severity',affected_families=jsonb_array_length(coalesce(payload->'families','[]')),
        affected_structures=coalesce((payload->>'affectedStructures')::integer,0),incident_datetime=(payload->>'reportDate')::date,
        status=case when old_status='returned' then 'pending' else old_status end where report_id=rid;
      delete from public.report_images where report_id=rid;
      delete from public.affected_families where report_id=rid;
    end if;
    for family in select value from jsonb_array_elements(coalesce(payload->'families','[]')) loop
      insert into public.affected_families(report_id,family_head_name,first_name,last_name,household_members,contact_number,evacuation_status,description,damage_severity,latitude,longitude)
      values(rid,trim(family->>'firstName') || ' ' || trim(family->>'lastName'),trim(family->>'firstName'),trim(family->>'lastName'),
        (family->>'householdMembers')::integer,family->>'contactNumber',family->>'evacuationStatus',family->>'description',
        coalesce(nullif(family->>'severity',''),payload->>'severity'),nullif(family->>'latitude','')::numeric,nullif(family->>'longitude','')::numeric) returning family_id into fid;
      for photo in select jsonb_array_elements_text(coalesce(family->'photos','[]')) loop
        insert into public.report_images(report_id,family_id,image_path) values(rid,fid,photo);
      end loop;
    end loop;
    for photo in select jsonb_array_elements_text(coalesce(payload->'photos','[]')) loop
      if not exists(select 1 from public.report_images where report_id=rid and image_path=photo) then
        insert into public.report_images(report_id,image_path) values(rid,photo);
      end if;
    end loop;
    result := mobile_private.report_json(rid);
  else
    if rid is null then
      insert into public.vehicular_accidents(user_id,location_id,accident_type,vehicle_type,involved_person_name,involved_person_first_name,involved_person_last_name,description,vehicles_involved,injured_count,fatality_count,incident_datetime,status)
      values(uid,lid,payload->>'accidentType',payload->>'vehicleType',payload->>'personName',payload->>'personFirstName',payload->>'personLastName',payload->>'description',
        coalesce((payload->>'vehiclesInvolved')::integer,1),coalesce((payload->>'injuredCount')::integer,0),coalesce((payload->>'fatalityCount')::integer,0),(payload->>'incidentDate')::date,'recorded') returning accident_id into rid;
    else
      update public.vehicular_accidents set location_id=lid,accident_type=payload->>'accidentType',vehicle_type=payload->>'vehicleType',
        involved_person_name=payload->>'personName',involved_person_first_name=payload->>'personFirstName',involved_person_last_name=payload->>'personLastName',description=payload->>'description',
        vehicles_involved=coalesce((payload->>'vehiclesInvolved')::integer,1),injured_count=coalesce((payload->>'injuredCount')::integer,0),fatality_count=coalesce((payload->>'fatalityCount')::integer,0),
        incident_datetime=(payload->>'incidentDate')::date,status=case when old_status='returned' then 'recorded' else old_status end where accident_id=rid;
      delete from public.accident_images where accident_id=rid;
      delete from public.accident_involved_persons where accident_id=rid;
    end if;
    for person in select value from jsonb_array_elements(coalesce(payload->'involvedPersons','[]')) loop
      insert into public.accident_involved_persons(accident_id,person_name,first_name,last_name,role,contact_number)
      values(rid,trim(person->>'firstName') || ' ' || trim(person->>'lastName'),trim(person->>'firstName'),trim(person->>'lastName'),person->>'role',person->>'contactNumber');
    end loop;
    for photo in select jsonb_array_elements_text(coalesce(payload->'photos','[]')) loop
      insert into public.accident_images(accident_id,image_path) values(rid,photo);
    end loop;
    result := mobile_private.accident_json(rid);
  end if;
  return result;
end $$;

-- The PHP server uses its existing database role. Mobile access is RPC-only.
revoke all on all tables in schema public from anon, authenticated;
revoke all on all sequences in schema public from anon, authenticated;
revoke all on all functions in schema mobile_private from public, anon, authenticated;
revoke all on function public.mobile_api(text,jsonb,bigint) from public,anon;
grant execute on function public.mobile_api(text,jsonb,bigint) to authenticated;

insert into storage.buckets(id,name,public,file_size_limit,allowed_mime_types)
values('mobile-evidence','mobile-evidence',false,10485760,array['image/jpeg','image/png','image/webp','image/heic','image/heif'])
on conflict(id) do nothing;
-- A policy helper reveals no account data and checks live account status for every request.
create or replace function public.mobile_can_access_photo(object_name text) returns boolean
language plpgsql stable security definer set search_path = '' as $$
begin
  perform mobile_private.officer_id();
  return split_part(object_name,'/',1)=auth.uid()::text;
exception when insufficient_privilege then return false;
end $$;
revoke all on function public.mobile_can_access_photo(text) from public,anon;
grant execute on function public.mobile_can_access_photo(text) to authenticated;
drop policy if exists mobile_evidence_read on storage.objects;
create policy mobile_evidence_read on storage.objects for select to authenticated
  using(bucket_id='mobile-evidence' and public.mobile_can_access_photo(name));
drop policy if exists mobile_evidence_insert on storage.objects;
create policy mobile_evidence_insert on storage.objects for insert to authenticated
  with check(bucket_id='mobile-evidence' and public.mobile_can_access_photo(name));
drop policy if exists mobile_evidence_delete on storage.objects;
create policy mobile_evidence_delete on storage.objects for delete to authenticated
  using(bucket_id='mobile-evidence' and public.mobile_can_access_photo(name));

notify pgrst, 'reload schema';
commit;
