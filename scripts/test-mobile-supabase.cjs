const assert = require('node:assert/strict');
const fs = require('node:fs');
const { randomUUID } = require('node:crypto');
const { createClient } = require('../disaster-reporting-expo/node_modules/@supabase/supabase-js');
const { createDatabaseClient, root, dotenv } = require('./supabase-db.cjs');
const env = dotenv.parse(fs.readFileSync(root + '/disaster-reporting-expo/.env'));
const makeClient = () => createClient(env.EXPO_PUBLIC_SUPABASE_URL, env.EXPO_PUBLIC_SUPABASE_ANON_KEY,
  { auth: { persistSession: false, autoRefreshToken: false } });
const clients = [makeClient(), makeClient()];
const password = 'Test-only-' + randomUUID();
const tag = randomUUID();
const ids = [];
const authIds = [];
const locations = [];
const records = [];
const db = createDatabaseClient();
async function rpc(client, action, payload = {}, record_id = null) {
  const { data, error } = await client.rpc('mobile_api', { action, payload, record_id });
  if (error) throw new Error(`${action}: ${error.message}`);
  if (action === 'save_report' || action === 'save_accident') {
    const table = action === 'save_report' ? 'disaster_reports' : 'vehicular_accidents';
    const key = action === 'save_report' ? 'report_id' : 'accident_id';
    const result = await db.query(`select location_id from public.${table} where ${key}=$1`,[data.id]);
    locations.push(result.rows[0].location_id);
  }
  return data;
}
async function main() {
  await db.connect();
  try {
    for (let i = 0; i < clients.length; i++) {
      const email = `mobile-test-${tag}-${i}@example.com`;
      const result = await db.query(`insert into public.users(role_id,full_name,email,password,status)
        select role_id,'Mobile integration fixture',$1,extensions.crypt($2,extensions.gen_salt('bf',4)),'active'
        from public.roles where role_name='field_officer' returning user_id`, [email, password]);
      ids.push(result.rows[0].user_id);
      const { data, error } = await clients[i].auth.signInWithPassword({ email, password });
      assert.ifError(error);
      authIds.push(data.user.id);
      assert.equal((await rpc(clients[i], 'profile')).role, 'field_officer');
    }
    console.log('PASS: provisioned officers sign in through Supabase Auth');
    const unauthenticated = await makeClient().rpc('mobile_api', { action: 'reports' });
    assert(unauthenticated.error);
    for (const table of ['users','personal_access_tokens','disaster_reports','affected_families']) {
      const { error } = await clients[0].from(table).select('*').limit(1);
      assert(error, `${table} must not expose raw rows`);
    }
    console.log('PASS: unauthenticated RPC and direct account/report table access denied');
    const barangays = await rpc(clients[0], 'barangays');
    assert(barangays.length);
    const report = { barangay: barangays[0].name, purok:'Integration test', description:'Synthetic integration fixture',
      disasterType:'Flood', severity:'minor', affectedStructures:1, latitude:'6.7',longitude:'125.3',reportDate:'2026-11-12',
      photos:[], families:[{firstName:'Test',lastName:'Family',householdMembers:3,contactNumber:'',evacuationStatus:'',
        description:'Fixture',severity:'minor',latitude:'6.7',longitude:'125.3',photos:[]}] };
    let saved = await rpc(clients[0], 'save_report', report);
    records.push(['report',saved.id]);
    assert.equal(saved.families[0].householdMembers,3);
    assert.equal(saved.status,'pending');
    assert.equal((await rpc(clients[1], 'reports')).length,0);
    await assert.rejects(rpc(clients[1], 'save_report',report,saved.id),/access denied/i);
    await assert.rejects(rpc(clients[1], 'delete_report',{},saved.id),/access denied/i);
    await assert.rejects(rpc(clients[0], 'save_report',{...report,latitude:'0',longitude:'0',families:[]}),/valid GPS/);
    await assert.rejects(rpc(clients[0], 'save_report',{...report,affectedStructures:-1}),/Invalid report/);
    await db.query("update public.disaster_reports set status='returned' where report_id=$1",[saved.id]);
    saved = await rpc(clients[0], 'save_report',{...report,description:'Edited fixture'},saved.id);
    assert.equal(saved.status,'pending');
    console.log('PASS: report CRUD, family mapping, GPS validation, ownership, returned-report resubmission');
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aT1cAAAAASUVORK5CYII=','base64');
    const object = `${authIds[0]}/${randomUUID()}.png`;
    assert.ifError((await clients[0].storage.from('mobile-evidence').upload(object,png,{contentType:'image/png'})).error);
    const reference = `supabase://mobile-evidence/${object}`;
    saved = await rpc(clients[0],'save_report',{...report,photos:[reference],families:[{...report.families[0],photos:[reference]}]},saved.id);
    assert.equal(saved.photos.length,0); // Aggregated family photos upload/save only once.
    assert.equal(saved.families[0].photos[0],reference);
    const signed = await clients[0].storage.from('mobile-evidence').createSignedUrl(object,60);
    assert.ifError(signed.error);
    assert.equal((await fetch(signed.data.signedUrl)).status,200);
    assert((await clients[1].storage.from('mobile-evidence').createSignedUrl(object,60)).error);
    assert((await clients[1].storage.from('mobile-evidence').upload(`${authIds[0]}/forbidden.png`,png,{contentType:'image/png'})).error);
    await assert.rejects(rpc(clients[1],'save_report',{...report,photos:[reference]}),/does not belong/);
    // Check editing with retained photo references.
    await rpc(clients[0],'save_report',{...report,families:[{...report.families[0],photos:[reference]}]},saved.id);
    console.log('PASS: private photo upload, signed download, retained photos, cross-account storage denial');
    const accident = { barangay:barangays[0].name,purok:'Test',roadSegment:'Test',personFirstName:'Test',personLastName:'Person',
      personName:'Test Person',accidentType:'Collision',vehicleType:'Car',description:'Synthetic integration fixture',
      vehiclesInvolved:2,injuredCount:1,fatalityCount:0,latitude:'6.7',longitude:'125.3',incidentDate:'2026-11-12',photos:[],
      involvedPersons:[{firstName:'Test',lastName:'Person',personName:'Test Person',role:'Driver',contactNumber:''}] };
    let a = await rpc(clients[0],'save_accident',accident);
    records.push(['accident',a.id]);
    assert.equal(a.involvedPersons[0].firstName,'Test');
    assert.equal((await rpc(clients[1],'accidents')).length,0);
    await assert.rejects(rpc(clients[1],'delete_accident',{},a.id),/access denied/i);
    await db.query("update public.vehicular_accidents set status='returned' where accident_id=$1",[a.id]);
    a = await rpc(clients[0],'save_accident',{...accident,injuredCount:2},a.id);
    assert.equal(a.status,'recorded');
    assert.equal(a.injuredCount,2);
    await rpc(clients[0],'delete_accident',{},a.id);
    await rpc(clients[0],'delete_report',{},saved.id);
    assert.equal((await rpc(clients[0],'reports')).length,0);
    assert.equal((await rpc(clients[0],'accidents')).length,0);
    console.log('PASS: accident CRUD, involved persons, ownership, resubmission, report/accident deletion');
    const newPassword = 'Updated-' + randomUUID();
    assert.ifError((await clients[0].auth.updateUser({password:newPassword})).error);
    const synced = await db.query('select password=extensions.crypt($2,password) as valid from public.users where user_id=$1',[ids[0],newPassword]);
    assert.equal(synced.rows[0].valid,true);
    await clients[0].auth.signOut();
    const email = `mobile-test-${tag}-0@example.com`;
    assert.ifError((await clients[0].auth.signInWithPassword({email,password:newPassword})).error);
    const {data:{session}} = await clients[0].auth.refreshSession();
    assert(session);
    await db.query("update public.users set status='inactive' where user_id=$1",[ids[0]]);
    await assert.rejects(rpc(clients[0],'reports'),/active Field Officer/);
    assert((await clients[0].storage.from('mobile-evidence').createSignedUrl(object,60)).error);
    await db.query("update public.users set status='active',role_id=(select role_id from public.roles where role_name='validator') where user_id=$1",[ids[0]]);
    await assert.rejects(rpc(clients[0],'reports'),/active Field Officer/);
    await db.query("update public.users set role_id=(select role_id from public.roles where role_name='field_officer') where user_id=$1",[ids[0]]);
    assert.ifError((await clients[0].storage.from('mobile-evidence').remove([object])).error);
    console.log('PASS: password synchronization, token refresh, immediate inactive/role access revocation');
  } finally {
    // Clean up only this run's synthetic fixtures, including locations from edits.
    for (const [kind,id] of records) {
      const table = kind==='report'?'disaster_reports':'vehicular_accidents';
      const key = kind==='report'?'report_id':'accident_id';
      if (kind==='report') await db.query('delete from public.affected_families where report_id=$1',[id]);
      else await db.query('delete from public.accident_involved_persons where accident_id=$1',[id]);
      await db.query(`delete from public.${table} where ${key}=$1`,[id]);
    }
    await db.query('delete from public.users where user_id=any($1::bigint[])',[ids]);
    await db.query('delete from auth.users where id=any($1::uuid[])',[authIds]);
    // Locations have no owner column; remove only the tracked test-created IDs.
    if (locations.length) await db.query('delete from public.incident_locations where location_id=any($1::bigint[])',[locations]);
    for (const client of clients) await client.auth.signOut().catch(()=>{});
    await db.end();
  }
}
main().catch(error => { console.error(error.message); process.exitCode=1; });
