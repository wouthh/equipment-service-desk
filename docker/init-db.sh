#!/bin/sh
set -eu
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<'SQL'
\getenv runtime_password DESK_RUNTIME_PASSWORD
CREATE ROLE desk_runtime LOGIN PASSWORD :'runtime_password';
GRANT USAGE ON SCHEMA public TO desk_runtime;
SQL
