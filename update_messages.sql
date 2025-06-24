-- Voeg is_read kolom toe aan messages tabel
ALTER TABLE messages ADD COLUMN is_read BOOLEAN DEFAULT FALSE;

-- Update bestaande berichten als gelezen
UPDATE messages SET is_read = TRUE; 