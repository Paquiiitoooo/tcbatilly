-- Migration: Ajout du champ event_time dans la table events
ALTER TABLE events 
ADD COLUMN event_time VARCHAR(50) DEFAULT '(journée)' 
AFTER event_date;

-- Mettre à jour les événements existants
UPDATE events SET event_time = '(journée)' WHERE event_time IS NULL;

