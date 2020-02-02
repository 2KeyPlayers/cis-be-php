-- heslo = 'heslo'
INSERT INTO uzivatel (id, prezyvka, heslo, email, meno, priezvisko) VALUES (1, 'Admin', '$2y$10$JywMck.jlPmvG\/WmjmYoOOXMkQVKigxglcl8gfrepGYZbUEDuHM3q', NULL, 'Janko', 'Hraško');
INSERT INTO uzivatel (id, prezyvka, heslo, email, meno, priezvisko, titul, veduci) VALUES (2, 'Veduca', '$2y$10$JywMck.jlPmvG\/WmjmYoOOXMkQVKigxglcl8gfrepGYZbUEDuHM3q', NULL, 'Marienka', 'Hrašková', 'Mgr.', TRUE);
SELECT setval('uzivatel_id_seq', (SELECT MAX(id) FROM uzivatel));

INSERT INTO kruzok (id, nazov, veduci) VALUES (1, 'Aerobkáčik', 2);
SELECT setval('kruzok_id_seq', (SELECT MAX(id) FROM kruzok));

INSERT INTO ucastnik (uzivatel, cislo_rozhodnutia, pohlavie, priezvisko, meno, datum_narodenia, adresa.mesto, adresa.cislo, kruzky) VALUES (1,1,'M','Juraj','Jánošík','2009-01-01','Terchová','1',ARRAY[1]);

insert prihlaska for each ucastnik + kruzok
DO
$do$
DECLARE
  u RECORD;
  k INT;
BEGIN
  FOR u IN SELECT id, kruzky
    FROM ucastnik
    ORDER BY id
  LOOP
    FOREACH k IN ARRAY u.kruzky
    LOOP
      INSERT INTO prihlaska (ucastnik, kruzok) VALUES (u.id, k);
    END LOOP;
  END LOOP;
END;
$do$
