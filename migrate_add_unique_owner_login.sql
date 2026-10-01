ALTER TABLE users
ADD UNIQUE KEY uq_users_owner_id (owner_id);
