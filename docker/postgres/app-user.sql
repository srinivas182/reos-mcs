-- REOS connects as a normal database user (ADR 0015): PostgreSQL row-level
-- security never applies to superusers, so tenant isolation would be skipped.
-- Runs on first start (docker-entrypoint-initdb.d) and from
-- scripts/dev-db-app-user.sh for databases created before this file existed.

DO $$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'reos_app') THEN
    CREATE ROLE reos_app LOGIN PASSWORD 'reos-app-local' CREATEDB NOSUPERUSER NOBYPASSRLS;
  END IF;
END
$$;

ALTER DATABASE reos OWNER TO reos_app;
ALTER DATABASE reos_testing OWNER TO reos_app;

\connect reos
ALTER SCHEMA public OWNER TO reos_app;
-- Tables made before this user existed (a table's sequences move with it).
DO $$
DECLARE t record;
BEGIN
  FOR t IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP
    EXECUTE format('ALTER TABLE public.%I OWNER TO reos_app', t.tablename);
  END LOOP;
END
$$;

\connect reos_testing
ALTER SCHEMA public OWNER TO reos_app;
-- Tables made before this user existed (a table's sequences move with it).
DO $$
DECLARE t record;
BEGIN
  FOR t IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP
    EXECUTE format('ALTER TABLE public.%I OWNER TO reos_app', t.tablename);
  END LOOP;
END
$$;
