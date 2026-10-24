#!/bin/bash
# Creates the database the test suite runs against.
#
# The MySQL image only creates the single database named by MYSQL_DATABASE, so
# without this the suite would have no argus_test to migrate, and running it
# would fall back to the development database and truncate the seeded data.
#
# The compose services connect as the "argus" user, so the test database has to
# be granted to that user as well.
set -e

mysql -uroot -proot <<-SQL
	CREATE DATABASE IF NOT EXISTS argus_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
	GRANT ALL PRIVILEGES ON argus_test.* TO 'argus'@'%';
	FLUSH PRIVILEGES;
SQL
