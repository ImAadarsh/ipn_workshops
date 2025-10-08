-- Add unique constraint to prevent duplicate error entries for same workshop and user
-- This ensures that for each workshop_id and user_id combination,
-- there can only be one entry in the email_errors table

ALTER TABLE email_errors 
ADD CONSTRAINT unique_workshop_user_error 
UNIQUE (workshop_id, user_id);
