const fs = require('node:fs');
const path = require('node:path');
const { randomUUID, createHash } = require('node:crypto');
const { createClient } = require('../disaster-reporting-expo/node_modules/@supabase/supabase-js');
const { createDatabaseClient, root, dotenv } = require('./supabase-db.cjs');
const env = dotenv.parse(fs.readFileSync(root+'/.env'));
const laravelEnvPath = root+'/matanao-disaster-ai/.env';
const laravel = dotenv.parse(fs.readFileSync(laravelEnvPath));
const db = createDatabaseClient();
async function main() {
  await db.connect();
  const client = createClient(env.SUPABASE_URL,env.SUPABASE_ANON_KEY,{auth:{persistSession:false,autoRefreshToken:false}});
  try {
    let email = laravel.SUPABASE_MEDIA_EMAIL;
    let password = laravel.SUPABASE_MEDIA_PASSWORD;
    if (!email || !password) {
      email = `media-bridge-${randomUUID()}@example.com`;
      password = randomUUID()+randomUUID();
      const id = randomUUID();
      await db.query('begin');
      try {
        await db.query(`insert into auth.users(instance_id,id,aud,role,email,encrypted_password,email_confirmed_at,
          confirmation_token,recovery_token,email_change_token_new,email_change,raw_app_meta_data,raw_user_meta_data,created_at,updated_at)
          values('00000000-0000-0000-0000-000000000000',$1,'authenticated','authenticated',$2,
          extensions.crypt($3,extensions.gen_salt('bf',12)),now(),'','','','','{"provider":"email","providers":["email"]}','{}',now(),now())`,[id,email,password]);
        await db.query(`insert into auth.identities(provider_id,user_id,identity_data,provider,created_at,updated_at)
          values($1::text,$1::uuid,jsonb_build_object('sub',$1::text,'email',$2::text,'email_verified',true),'email',now(),now())`,[id,email]);
        await db.query('insert into mobile_private.media_service(auth_id) values($1)',[id]);
        const settings = { SUPABASE_URL:env.SUPABASE_URL,SUPABASE_ANON_KEY:env.SUPABASE_ANON_KEY,SUPABASE_MEDIA_EMAIL:email,SUPABASE_MEDIA_PASSWORD:password };
        let contents = fs.readFileSync(laravelEnvPath,'utf8');
        for (const [key,value] of Object.entries(settings)) {
          const pattern = new RegExp(`^${key}=.*$`,'m');
          contents = pattern.test(contents)?contents.replace(pattern,`${key}=${value}`):contents+`\n${key}=${value}`;
        }
        fs.writeFileSync(laravelEnvPath,contents.trimEnd()+'\n');
        await db.query('commit');
      } catch(error) { await db.query('rollback'); throw error; }
    }
    const {error} = await client.auth.signInWithPassword({email,password});
    if(error) throw error;
    const {rows} = await db.query(`select 'report_images' as source,i.image_id,i.image_path,a.auth_id from public.report_images i
      join public.disaster_reports r using(report_id) left join mobile_private.accounts a on a.user_id=r.user_id
      union all select 'accident_images',i.image_id,i.image_path,a.auth_id from public.accident_images i
      join public.vehicular_accidents r using(accident_id) left join mobile_private.accounts a on a.user_id=r.user_id`);
    let migrated=0, missing=0;
    for(const row of rows) {
      if(row.image_path.startsWith('supabase://')) continue;
      const existing = await db.query('select storage_reference from mobile_private.legacy_media where original_path=$1',[row.image_path]);
      let reference=existing.rows[0]?.storage_reference;
      if(!reference) {
        const relative = row.image_path.replace(/\\/g,'/').replace(/^https?:\/\/[^/]+\//,'').replace(/^(public\/)?storage\//,'');
        const base = path.resolve(root,'matanao-disaster-ai/storage/app/public');
        const local = path.resolve(base,relative);
        if(!local.startsWith(base+path.sep)) throw new Error('Media path is outside the Laravel public storage directory');
        if(!fs.existsSync(local)) { missing++; continue; }
        const ext=path.extname(local).slice(1).toLowerCase();
        const object=`${row.auth_id || 'legacy'}/legacy/${createHash('sha256').update(row.image_path).digest('hex')}.${ext}`;
        const uploaded=await client.storage.from('mobile-evidence').upload(object,fs.readFileSync(local),{contentType:`image/${ext==='jpg'?'jpeg':ext}`});
        if(uploaded.error && uploaded.error.statusCode!=='409') throw uploaded.error;
        reference='supabase://mobile-evidence/'+object;
        await db.query('insert into mobile_private.legacy_media(original_path,storage_reference) values($1,$2) on conflict do nothing',[row.image_path,reference]);
      }
      await db.query(`update public.${row.source} set image_path=$1 where image_id=$2 and image_path=$3`,[reference,row.image_id,row.image_path]);
      migrated++;
    }
    console.log(`Migrated ${migrated} existing photo references to private Supabase Storage. Missing local files: ${missing}.`);
    if(missing) process.exitCode=2;
  } finally { await client.auth.signOut(); await db.end(); }
}
main().catch(error=>{console.error(error.message);process.exitCode=1;});
