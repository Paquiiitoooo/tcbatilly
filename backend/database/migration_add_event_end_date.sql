-- Date de fin optionnelle pour les événements qui durent plusieurs jours
-- (tournois, stages). À importer une seule fois.
ALTER TABLE events ADD COLUMN IF NOT EXISTS event_end_date DATE DEFAULT NULL AFTER event_date;
