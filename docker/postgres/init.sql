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

-- Parallel test databases are copies of template1 (pgvector for the knowledge base, A5.3).
\connect template1
CREATE EXTENSION IF NOT EXISTS vector;
