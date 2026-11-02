-- Fikstura zrzutu starej aplikacji (laptopservice / laptop_images / service_history / users).
-- Służy do testu importera (T2.7) — nie zawiera danych produkcyjnych.
INSERT INTO `users` (`Login`, `Haslo`, `created_at`, `updated_at`) VALUES
('admin', 'admin', '2024-01-10 09:00:00', '2024-01-10 09:00:00'),
('tomek', 'tomek123', '2024-02-11 10:00:00', '2024-02-11 10:00:00');

INSERT INTO `laptopservice` (`Id`, `nr_tel`, `model_laptopa`, `usterka`, `haslo`, `serial_number`, `express`, `potwierdzenie_odbioru`, `uwagi`, `planowany_czas_wydania`, `cena`, `koszty`, `plomba`, `status`, `godzina_wydania`, `przyjety_przez`, `created_at`, `updated_at`) VALUES
(1, '600 100 200', 'Dell Latitude 5420', 'Brak ladowania', NULL, 'SN-1', 0, 0, NULL, '2026-02-20 15:00:00', '199,99', '50.00', 'PLO-001-2024', 'w_trakcie', NULL, 'admin', '2026-02-14 10:00:00', '2026-02-14 12:00:00'),
(2, '505 123 456', 'Lenovo ThinkPad T490', 'Wymiana klawiatury', 'haslo123', 'SN-2', 1, 1, 'Klient dzwonil', '2026-02-18 12:00:00', 300.00, 120.00, 'PLO-002-2024', 'wydany', '2026-02-17 12:30:00', 'tomek', '2026-02-12 09:30:00', '2026-02-17 12:30:00'),
(3, '0048505123999', 'HP EliteBook 840', 'Nie wstaje', NULL, NULL, 0, 0, NULL, NULL, 0.00, 0.00, NULL, 'dziwny_status', NULL, 'nieznany', NULL, '2026-03-01 08:00:00');

INSERT INTO `laptop_images` (`id`, `laptop_id`, `image_path`, `uploaded_at`) VALUES
(1, 2, 'uploads/2/foto1.jpg', '2026-02-12 09:40:00'),
(2, 2, '/serwis/uploads/2/foto2.jpg', '2026-02-12 09:41:00');

INSERT INTO `service_history` (`id`, `service_id`, `action_type`, `performed_by`, `performed_at`, `description`, `previous_values`, `new_values`) VALUES
(1, 1, 'accepted', 'admin', '2026-02-14 10:00:00', 'Przyjecie', NULL, '{"status":"oczekujacy"}'),
(2, 1, 'status_changed', 'admin', '2026-02-14 12:00:00', 'Rozpoczecie naprawy', '{"status":"oczekujacy"}', '{"status":"w_trakcie"}');
