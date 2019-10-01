CREATE TABLE uzivatel (
  id SERIAL PRIMARY KEY,
  prezyvka TEXT NOT NULL,
  heslo VARCHAR(60),
  email TEXT,
  meno TEXT NOT NULL,
  priezvisko TEXT NOT NULL,
  titul TEXT,
  veduci BOOLEAN DEFAULT FALSE,
  UNIQUE (prezyvka)
);

CREATE TABLE kruzok (
  id SERIAL PRIMARY KEY,
  nazov TEXT NOT NULL,
  veduci INT NOT NULL REFERENCES uzivatel(id),
  zadarmo BOOLEAN NOT NULL DEFAULT FALSE,
  -- TODO: vytvoreny, upraveny, uzivatel
  UNIQUE (nazov)
);
-- ALTER TABLE kruzok ADD COLUMN zadarmo BOOLEAN NOT NULL DEFAULT FALSE;

CREATE TYPE pohlavie AS ENUM ('M', 'Z');
CREATE TABLE ucastnik (
  id SERIAL PRIMARY KEY,
  cislo_roznodnutia INT NOT NULL,
  pohlavie pohlavie NOT NULL,
  meno TEXT NOT NULL,
  priezvisko TEXT NOT NULL,
  datum_narodenia DATE NOT NULL,
  mesto TEXT NOT NULL,
  ulica TEXT,
  cislo TEXT,
  kruzky INT ARRAY,
  -- TODO: vytvoreny, upraveny, uzivatel
  UNIQUE (cislo_roznodnutia),
  UNIQUE (meno, priezvisko, datum_narodenia)
);

CREATE TYPE platba AS (
  suma MONEY,
  datum DATE,
  uzivatel INT
);
CREATE TABLE poplatky (
  ucastnik INT NOT NULL REFERENCES ucastnik(id),
  kruzok INT NOT NULL REFERENCES kruzok(id),
  poplatok MONEY NOT NULL DEFAULT 4,
  stav CHAR(9) NOT NULL DEFAULT '---------',
  platby platba ARRAY,
  UNIQUE (ucastnik, kruzok)
);
-- insert poplatky for each ucastnik + kruzok
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
      INSERT INTO poplatky (ucastnik, kruzok) VALUES (u.id, k);
    END LOOP;
  END LOOP;
END;
$do$
