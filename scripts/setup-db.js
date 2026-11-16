'use strict';

require('dotenv').config({ quiet: true });

const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');

const dbName = process.env.DB_NAME || 'toko_osing_db';

const connectionOptions = {
  host: process.env.DB_HOST || '127.0.0.1',
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: dbName,
  multipleStatements: true,
  ssl: process.env.DB_SSL === 'true' ? { rejectUnauthorized: false } : undefined,
};

function readSql(file) {
  const sql = fs.readFileSync(path.join(__dirname, '..', 'database', file), 'utf8');
  return sql
    .replace(/CREATE\s+DATABASE[\s\S]*?;/gi, '')
    .replace(/^\s*USE\s+[^;]+;/gim, '');
}

async function main() {
  const connection = await mysql.createConnection(connectionOptions);
  console.log(`Menyiapkan tabel dan data dummy di database "${dbName}"...`);

  await connection.query('SET FOREIGN_KEY_CHECKS = 0');
  for (const table of ['products', 'categories', 'users', 'sellers']) {
    await connection.query(`DROP TABLE IF EXISTS \`${table}\``);
  }
  await connection.query('SET FOREIGN_KEY_CHECKS = 1');

  await connection.query(readSql('schema.sql'));
  await connection.query(readSql('seed.sql'));

  const [[counts]] = await connection.query(
    `SELECT (SELECT COUNT(*) FROM users) AS users,
            (SELECT COUNT(*) FROM sellers) AS sellers,
            (SELECT COUNT(*) FROM categories) AS categories,
            (SELECT COUNT(*) FROM products) AS products`
  );

  console.log('Selesai.');
  console.log(`  users      ${counts.users}`);
  console.log(`  sellers    ${counts.sellers}`);
  console.log(`  categories ${counts.categories}`);
  console.log(`  products   ${counts.products}`);
  console.log('');
  console.log('Akun demo:');
  console.log('  admin  : admin@tokoosing.test / admin123');
  console.log('  seller : batik.kemiren@tokoosing.test / seller123');
  console.log('  buyer  : buyer@tokoosing.test / buyer123');

  await connection.end();
}

main().catch((error) => {
  console.error('Gagal menyiapkan database:', error.message);
  process.exitCode = 1;
});

