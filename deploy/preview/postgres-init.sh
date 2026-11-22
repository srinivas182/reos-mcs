#!/bin/sh
# First start of the preview database: the extensions REOS needs, and the
# normal (non-superuser) app role, so row-level security applies (ADR 0015).
set -e
psql -v ON_ERROR_STOP=1 --username reos --dbname reos <<SQL
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS citext;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE ROLE reos_app LOGIN PASSWORD '${REOS_APP_DB_PASSWORD}' NOSUPERUSER NOBYPASSRLS;
ALTER DATABASE reos OWNER TO reos_app;
ALTER SCHEMA public OWNER TO reos_app;
SQL
