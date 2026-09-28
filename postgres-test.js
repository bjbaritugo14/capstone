const fs = require('node:fs');
const path = require('node:path');
const { Client } = require('pg');

function loadDotEnv(filePath) {
  if (!fs.existsSync(filePath)) {
    return;
  }

  const lines = fs.readFileSync(filePath, 'utf8').split(/\r?\n/);
  for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#') || !trimmed.includes('=')) {
      continue;
    }

    const separator = trimmed.indexOf('=');
    const key = trimmed.slice(0, separator).trim();
    let value = trimmed.slice(separator + 1).trim();

    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }

    if (key && process.env[key] === undefined) {
      process.env[key] = value;
    }
  }
}

loadDotEnv(path.join(__dirname, '.env'));

const connectionString = process.env.DATABASE_URL;

if (!connectionString) {
  console.error('Missing DATABASE_URL.');
  console.error('Add your Supabase Postgres connection string to .env.');
  process.exit(1);
}

async function main() {
  const client = new Client({
    connectionString,
    ssl: {
      rejectUnauthorized: false,
    },
  });

  await client.connect();
  const result = await client.query('select current_database() as database, current_user as user, now() as connected_at');
  await client.end();

  console.log('Postgres connection successful:');
  console.log(JSON.stringify(result.rows[0], null, 2));
}

main().catch((error) => {
  console.error('Postgres connection failed:');
  console.error(error.message || error);
  process.exit(1);
});
