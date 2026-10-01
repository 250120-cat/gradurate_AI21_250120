-- Migration: add genre column to consultation_threads
ALTER TABLE consultation_threads
ADD COLUMN genre VARCHAR(100) NOT NULL DEFAULT 'general';

-- Optional: add index for faster filtering
CREATE INDEX IF NOT EXISTS idx_consultation_genre ON consultation_threads (genre);
