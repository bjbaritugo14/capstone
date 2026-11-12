begin;
create table if not exists mobile_private.media_service (
  auth_id uuid primary key references auth.users(id) on delete cascade
);
create table if not exists mobile_private.legacy_media (
  original_path text primary key,
  storage_reference text not null
);
create or replace function public.mobile_is_media_service() returns boolean
language sql stable security definer set search_path = '' as $$
select exists(select 1 from mobile_private.media_service where auth_id=auth.uid());
$$;
revoke all on function public.mobile_is_media_service() from public,anon;
grant execute on function public.mobile_is_media_service() to authenticated;
drop policy if exists mobile_evidence_read on storage.objects;
create policy mobile_evidence_read on storage.objects for select to authenticated
  using(bucket_id='mobile-evidence' and (public.mobile_is_media_service() or public.mobile_can_access_photo(name)));
drop policy if exists mobile_evidence_insert on storage.objects;
create policy mobile_evidence_insert on storage.objects for insert to authenticated
  with check(bucket_id='mobile-evidence' and (public.mobile_is_media_service() or public.mobile_can_access_photo(name)));
revoke all on all tables in schema mobile_private from public,anon,authenticated;
commit;
