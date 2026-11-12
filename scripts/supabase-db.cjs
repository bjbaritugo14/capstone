const fs = require('node:fs');
const path = require('node:path');
const { Client } = require('pg');
const dotenv = require('../disaster-reporting-expo/node_modules/dotenv');
const root = path.resolve(__dirname, '..');
const env = dotenv.parse(fs.readFileSync(path.join(root, 'matanao-disaster-ai/.env')));
function createDatabaseClient() {
  return new Client({ host: env.DB_HOST, port: Number(env.DB_PORT), database: env.DB_DATABASE,
    user: env.DB_USERNAME, password: env.DB_PASSWORD, ssl: { rejectUnauthorized: false } });
}
async function main() {
  const db = new Client({ host: env.DB_HOST, port: Number(env.DB_PORT), database: env.DB_DATABASE,
    user: env.DB_USERNAME, password: env.DB_PASSWORD, ssl: { rejectUnauthorized: false } });
  await db.connect();
  try {
    const sql = process.argv[2] ? fs.readFileSync(path.resolve(process.argv[2]), 'utf8') : `
      select table_schema, table_name, column_name, data_type, is_nullable, column_default
      from information_schema.columns where (table_schema='public' and table_name in ('affected_families','damage_reports','disaster_reports','accident_involved_persons','accident_validations')) or (table_schema='auth' and table_name in ('users','identities')) order by table_schema,table_name,ordinal_position;
      select schemaname, tablename, policyname, roles, cmd from pg_policies where schemaname in ('public','storage');
      select count(*) as auth_users from auth.users;
      select role_name, count(*) from public.users join public.roles using(role_id) group by role_name;
      select id, public from storage.buckets;
    `;
    const result = await db.query(sql);
    for (const item of Array.isArray(result) ? result : [result]) {
      if (item.rows.length) console.log(JSON.stringify(item.rows));
    }
  } finally { await db.end(); }
}
module.exports = { createDatabaseClient, root, dotenv };
if (require.main === module) main().catch(error => { console.error(error.message); process.exitCode = 1; });
