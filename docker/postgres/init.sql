-- Runs once, when the local PostgreSQL volume is first created.
-- Extensions REOS relies on, in the app database and the test database.

CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS citext;
CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE DATABASE reos_testing OWNER reos;
\connect reos_testing
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS citext;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
