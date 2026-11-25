-- ============================================
-- TC BATILLY - Offres d'adhésion 2026-2027 (d'après les offres du club sur Ten'Up)
-- ============================================
-- ATTENTION : remplace tous les tarifs actuels du site.
-- Le prix 0 signifie « voir le prix sur Ten'Up » (aucun montant affiché).

DELETE FROM pricing;

INSERT INTO pricing (name, badge, badge_type, price, period, features, note, display_order, is_active) VALUES
('Saison avec entraînements', 'Adhésion', 'default', 145.00, 'à partir de', '["Saison 2026-2027, du 1er septembre 2026 au 31 août 2027", "30 semaines d''entraînement"]', NULL, 1, 1),
('Mini tennis', 'Adhésion', 'default', 95.00, 'tarif unique', '["Saison 2026-2027, du 1er septembre 2026 au 31 août 2027", "Enfants jusqu''à 6 ans"]', NULL, 2, 1),
('Saison loisir', 'Adhésion', 'default', 65.00, 'à partir de', '["Adhésion à l''année, sans entraînements", "Accès aux courts sur réservation via Ten''Up"]', NULL, 3, 1),
('Formule été', 'Adhésion', 'default', 35.00, 'à partir de', '["Accès aux courts, en loisir", "De mars à août"]', NULL, 4, 1),
('Formule découverte', 'Adhésion', 'default', 20.00, 'à partir de', '["Pour essayer le tennis", "Valable 3 mois"]', NULL, 5, 1),
('Beach tennis', 'Adhésion', 'default', 0.00, '', '["Licence de beach tennis uniquement, sans l''adhésion", "Pour l''adhésion, contacter l''association de beach tennis", "Inutile avec une licence multi-raquettes FFT (tennis, padel, beach)"]', NULL, 6, 1);
