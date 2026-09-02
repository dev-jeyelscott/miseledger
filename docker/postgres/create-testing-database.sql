SELECT 'CREATE DATABASE miseledger_test'
WHERE NOT EXISTS (
    SELECT FROM pg_database
    WHERE datname = 'miseledger_test'
)\gexec
