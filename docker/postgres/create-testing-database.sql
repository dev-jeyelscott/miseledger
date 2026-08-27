SELECT 'CREATE DATABASE miseledger_testing'
WHERE NOT EXISTS (
    SELECT FROM pg_database
    WHERE datname = 'miseledger_testing'
)\gexec
