-- ============================================
-- TC BATILLY - Calendrier de la saison 2026-2027
-- Source : « Calendrier 2027 v1 » (planning du 31 août 2026 au 29 août 2027)
-- ============================================
-- À importer APRÈS migration_add_event_end_date.sql.
-- Ne supprime rien : les événements déjà présents restent en place.
--
-- Les stages (vacances scolaires) sont insérés INACTIFS : dans le planning ils
-- figurent sous « Dispo Stages ». Activez-les dans l'admin une fois confirmés.
-- Les colonnes « Dispo Plateaux / TMC / Stages » (samedis) ne sont pas importées.

INSERT INTO events (title, description, event_date, event_end_date, day, month, category, is_active) VALUES
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : Ligue Dames et Hommes 35-45-55.', '2026-09-06', NULL, '6', 'Sept', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : Ligue Dames et Hommes 35-45-55.', '2026-09-13', NULL, '13', 'Sept', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : Ligue Dames et Hommes 35-45-55.', '2026-09-20', NULL, '20', 'Sept', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : Ligue Dames et Hommes 35-45-55.', '2026-09-27', NULL, '27', 'Sept', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Jeunes 13-14 / 15-16 ans; Ligue Dames et Hommes 35-45-55.', '2026-10-04', NULL, '4', 'Oct', 'tournoi', 1),
('Tournoi hommes', 'Tournoi hommes, du samedi 10 octobre au samedi 31 octobre 2026.', '2026-10-10', '2026-10-31', '10-31', 'Oct', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Jeunes 13-14 / 15-16 ans.', '2026-10-11', NULL, '11', 'Oct', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Jeunes 13-14 / 15-16 ans.', '2026-10-18', NULL, '18', 'Oct', 'tournoi', 1),
('Stage, vacances de la Toussaint', 'Stage, du lundi 19 octobre au vendredi 23 octobre 2026.', '2026-10-19', '2026-10-23', '19-23', 'Oct', 'stage', 0),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Jeunes 13-14 / 15-16 ans.', '2026-10-25', NULL, '25', 'Oct', 'tournoi', 1),
('Stage, vacances de la Toussaint', 'Stage, du lundi 26 octobre au vendredi 30 octobre 2026.', '2026-10-26', '2026-10-30', '26-30', 'Oct', 'stage', 0),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Jeunes 13-14 / 15-16 ans.', '2026-11-01', NULL, '1', 'Nov', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Jeunes 13-14 / 15-16 ans.', '2026-11-08', NULL, '8', 'Nov', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Dames seniors (hiver); Ligue Jeunes 11-12 ans; Ligue Dames seniors (hiver).', '2026-11-15', NULL, '15', 'Nov', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Dames seniors (hiver); Ligue Jeunes 11-12 ans; Ligue Dames seniors (hiver).', '2026-11-22', NULL, '22', 'Nov', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Dames seniors (hiver); Ligue Jeunes 11-12 ans; Ligue Dames seniors (hiver).', '2026-11-29', NULL, '29', 'Nov', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Dames seniors (hiver); Ligue Jeunes 11-12 ans; Ligue Dames seniors (hiver).', '2026-12-06', NULL, '6', 'Déc', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Dames seniors (hiver); Ligue Jeunes 11-12 ans; Ligue Dames seniors (hiver).', '2026-12-13', NULL, '13', 'Déc', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Hommes seniors (hiver); Ligue Hommes seniors (hiver); Ligue Jeunes 11-12 ans, à confirmer.', '2027-01-10', NULL, '10', 'Janv', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Hommes seniors (hiver); Ligue Hommes seniors (hiver); Ligue Jeunes 11-12 ans, à confirmer.', '2027-01-17', NULL, '17', 'Janv', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Hommes seniors (hiver); Ligue Hommes seniors (hiver); Ligue Jeunes 11-12 ans, à confirmer.', '2027-01-24', NULL, '24', 'Janv', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Hommes seniors (hiver); Ligue Hommes seniors (hiver); Coupe des Dames (Ligue).', '2027-01-31', NULL, '31', 'Janv', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Hommes seniors (hiver); Ligue Hommes seniors (hiver).', '2027-02-07', NULL, '7', 'Févr', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : Coupe des Dames (Ligue).', '2027-02-14', NULL, '14', 'Févr', 'tournoi', 1),
('Tournoi dames', 'Tournoi dames, du samedi 20 février au dimanche 7 mars 2027.', '2027-02-20', '2027-03-07', '20-7', 'Févr-Mars', 'tournoi', 1),
('Stage, vacances d''hiver', 'Stage, du lundi 22 février au vendredi 26 février 2027.', '2027-02-22', '2027-02-26', '22-26', 'Févr', 'stage', 0),
('Stage, vacances d''hiver', 'Stage, du lundi 1er mars au vendredi 5 mars 2027.', '2027-03-01', '2027-03-05', '1-5', 'Mars', 'stage', 0),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Dames et Hommes 35-45-55; Coupe des Dames (Ligue).', '2027-03-07', NULL, '7', 'Mars', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Dames et Hommes 35-45-55.', '2027-03-14', NULL, '14', 'Mars', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Dames et Hommes 35-45-55; Coupe des Dames (Ligue).', '2027-03-21', NULL, '21', 'Mars', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Dames et Hommes 35-45-55.', '2027-03-28', NULL, '28', 'Mars', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Dames et Hommes 35-45-55; Coupe des Dames (Ligue).', '2027-04-04', NULL, '4', 'Avr', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : Coupe des Dames (Ligue).', '2027-04-18', NULL, '18', 'Avr', 'tournoi', 1),
('Stage, vacances de printemps', 'Stage, du lundi 19 avril au vendredi 23 avril 2027.', '2027-04-19', '2027-04-23', '19-23', 'Avr', 'stage', 0),
('Stage, vacances de printemps', 'Stage, du lundi 26 avril au vendredi 30 avril 2027.', '2027-04-26', '2027-04-30', '26-30', 'Avr', 'stage', 0),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Jeunes 11-12 ans.', '2027-06-06', NULL, '6', 'Juin', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Jeunes 11-12 ans; Coupe Crédit Mutuel (Ligue).', '2027-06-13', NULL, '13', 'Juin', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Jeunes 11-12 ans; Coupe Crédit Mutuel (Ligue).', '2027-06-20', NULL, '20', 'Juin', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Jeunes 11-12 ans; Coupe Crédit Mutuel (Ligue).', '2027-06-27', NULL, '27', 'Juin', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : CD54 Jeunes 11-12 ans; Coupe Crédit Mutuel (Ligue).', '2027-07-04', NULL, '4', 'Juil', 'tournoi', 1),
('Stage, début des vacances d''été', 'Stage, du lundi 5 juillet au vendredi 9 juillet 2027.', '2027-07-05', '2027-07-09', '5-9', 'Juil', 'stage', 0),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : Coupe Crédit Mutuel (Ligue).', '2027-07-18', NULL, '18', 'Juil', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : Coupe Crédit Mutuel (Ligue), à confirmer.', '2027-08-01', NULL, '1', 'Août', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : Coupe Crédit Mutuel (Ligue), à confirmer.', '2027-08-08', NULL, '8', 'Août', 'tournoi', 1),
('Championnat par équipes', 'Journée de championnat par équipes. Au programme : Coupe Crédit Mutuel (Ligue), à confirmer.', '2027-08-15', NULL, '15', 'Août', 'tournoi', 1);
