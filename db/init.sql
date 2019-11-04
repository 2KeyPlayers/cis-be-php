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
  vytvoreny DATE NOT NULL DEFAULT CURRENT_DATE,
  upraveny DATE,
  uzivatel INT NOT NULL DEFAULT 1 REFERENCES uzivatel(id),
  UNIQUE (nazov)
);
-- ALTER TABLE kruzok ADD COLUMN zadarmo BOOLEAN NOT NULL DEFAULT FALSE;

CREATE TYPE pohlavie AS ENUM ('M', 'Z');
CREATE TYPE adresa AS (
  mesto TEXT,
  ulica TEXT,
  cislo TEXT,
  psc TEXT
);
CREATE TABLE ucastnik (
  id SERIAL PRIMARY KEY,
  cislo_rozhodnutia INT NOT NULL,
  pohlavie pohlavie NOT NULL,
  meno TEXT NOT NULL,
  priezvisko TEXT NOT NULL,
  datum_narodenia DATE NOT NULL,
  adresa adresa NOT NULL,
  kruzky INT ARRAY,
  vytvoreny DATE NOT NULL DEFAULT CURRENT_DATE,
  upraveny DATE,
  uzivatel INT NOT NULL DEFAUL 1 REFERENCES uzivatel(id),
  UNIQUE (cislo_rozhodnutia),
  UNIQUE (meno, priezvisko, datum_narodenia)
);
-- ALTER TABLE ucastnik ADD COLUMN skola TEXT;
-- ALTER TABLE ucastnik ADD COLUMN trieda TEXT;
-- ALTER TABLE ucastnik ADD COLUMN zastupca TEXT;
-- ALTER TABLE ucastnik ADD COLUMN telefon TEXT;

CREATE TYPE platba AS (
  suma NUMERIC(6, 2), -- MONEY,
  datum DATE,
  uzivatel INT
);
CREATE TABLE poplatky (
  ucastnik INT NOT NULL REFERENCES ucastnik(id),
  kruzok INT NOT NULL REFERENCES kruzok(id),
  poplatok NUMERIC(6, 2) NOT NULL DEFAULT 4, -- MONEY
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

-- trigger after updates/deletes
CREATE TABLE kruzok_log(
  operacia CHAR(1) NOT NULL,
  datum TIMESTAMP NOT NULL,
  uzivatel INT REFERENCES uzivatel(id),
  udaje JSON 
);

CREATE OR REPLACE FUNCTION process_kruzok_log() RETURNS TRIGGER as
$$
  BEGIN
    IF (TG_OP = 'DELETE') THEN
      INSERT INTO kruzok_log SELECT 'D', now(), null, json_build_object('id', OLD.id, 'nazov', OLD.nazov, 'veduci', OLD.veduci, 'zadarmo', OLD.zadarmo);
      RETURN OLD;
    ELSIF (TG_OP = 'UPDATE') THEN
      INSERT INTO kruzok_log SELECT 'U', now(), NEW.uzivatel, json_build_object('id', OLD.id, 'nazov', OLD.nazov, 'veduci', OLD.veduci, 'zadarmo', OLD.zadarmo);
      RETURN NEW;
    END IF;
    RETURN NULL; -- result is ignored since this is an AFTER trigger
  END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER kruzok_log
AFTER UPDATE OR DELETE ON kruzok
FOR EACH ROW EXECUTE PROCEDURE process_kruzok_log();

-- trigger after updates/deletes
CREATE TABLE ucastnik_log(
  operacia CHAR(1) NOT NULL,
  datum TIMESTAMP NOT NULL,
  uzivatel INT REFERENCES uzivatel(id),
  udaje JSON 
);

CREATE OR REPLACE FUNCTION process_ucastnik_log() RETURNS TRIGGER as
$$
  BEGIN
    IF (TG_OP = 'DELETE') THEN
      INSERT INTO ucastnik_log SELECT 'D', now(), null, json_build_object('id', OLD.id, 'cisloRozhodnutia', OLD.cislo_rozhodnutia, 'pohlavie', OLD.pohlavie, 'meno', OLD.meno, 'priezvisko', OLD.priezvisko, 'datumNarodenia', OLD.datum_narodenia, 'adresa', OLD.adresa);
      RETURN OLD;
    ELSIF (TG_OP = 'UPDATE') THEN
      INSERT INTO ucastnik_log SELECT 'U', now(), NEW.uzivatel, json_build_object('id', OLD.id, 'cisloRozhodnutia', OLD.cislo_rozhodnutia, 'pohlavie', OLD.pohlavie, 'meno', OLD.meno, 'priezvisko', OLD.priezvisko, 'datumNarodenia', OLD.datum_narodenia, 'adresa', OLD.adresa);
      RETURN NEW;
    END IF;
    RETURN NULL; -- result is ignored since this is an AFTER trigger
  END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER ucastnik_log
AFTER UPDATE OR DELETE ON ucastnik
FOR EACH ROW EXECUTE PROCEDURE process_ucastnik_log();

