select 'reports' as kind,count(*) from public.disaster_reports
union all select 'accidents',count(*) from public.vehicular_accidents
union all select 'report_images',count(*) from public.report_images
union all select 'accident_images',count(*) from public.accident_images;
select count(*) as provisioned_officers from mobile_private.accounts;
select c.relname,c.relrowsecurity,has_table_privilege('anon',c.oid,'select') as anon_read,
  has_table_privilege('authenticated',c.oid,'select') as authenticated_read
from pg_class c join pg_namespace n on n.oid=c.relnamespace where n.nspname='public' and c.relkind='r' order by c.relname;
