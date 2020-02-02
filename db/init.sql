DROP TABLE prihlaska;
DROP TABLE ucastnik_log;
DROP TABLE ucastnik;
DROP TABLE kruzok_log;
DROP TABLE kruzok;
DROP TABLE uzivatel;
DROP TYPE pohlavie;
DROP TYPE adresa;
DROP TYPE platba;

-->> UZIVATEL <<--

CREATE TABLE uzivatel (
  id SERIAL PRIMARY KEY,
  prezyvka TEXT NOT NULL,
  heslo TEXT,
  email TEXT,
  meno TEXT NOT NULL,
  priezvisko TEXT NOT NULL,
  titul TEXT,
  veduci BOOLEAN DEFAULT FALSE,
  UNIQUE (prezyvka)
);

-->> KRUZOK <<--

CREATE TABLE kruzok (
  id SERIAL PRIMARY KEY,
  nazov TEXT NOT NULL,
  veduci INT NOT NULL REFERENCES uzivatel(id),
  zadarmo BOOLEAN NOT NULL DEFAULT FALSE,
  vytvoreny DATE NOT NULL DEFAULT CURRENT_DATE,
  upraveny DATE,
  uzivatel INT NOT NULL REFERENCES uzivatel(id),
  UNIQUE (nazov)
);

-->> UCASTNIK <<--

CREATE TYPE pohlavie AS ENUM ('M', 'Z');
CREATE TYPE adresa AS (
  mesto TEXT,
  ulica TEXT,
  cislo TEXT,
  psc TEXT
);
CREATE TYPE platba AS (
  suma NUMERIC(6, 2), -- MONEY,
  datum DATE,
  uzivatel INT
);
CREATE TABLE ucastnik (
  id SERIAL PRIMARY KEY,
  cislo_rozhodnutia INT NOT NULL,
  pohlavie pohlavie NOT NULL,
  meno TEXT NOT NULL,
  priezvisko TEXT NOT NULL,
  datum_narodenia DATE NOT NULL,
  adresa adresa NOT NULL,
  skola TEXT,
  trieda TEXT,
  zastupca TEXT,
  telefon TEXT,
  platby platba ARRAY,
  kruzky INT ARRAY,
  vytvoreny DATE NOT NULL DEFAULT CURRENT_DATE,
  upraveny DATE,
  uzivatel INT NOT NULL REFERENCES uzivatel(id),
  UNIQUE (cislo_rozhodnutia),
  UNIQUE (meno, priezvisko, datum_narodenia)
);

CREATE TABLE prihlaska (
  ucastnik INT NOT NULL REFERENCES ucastnik(id),
  kruzok INT NOT NULL REFERENCES kruzok(id),
  poplatok NUMERIC(6, 2) NOT NULL DEFAULT 4, -- MONEY
  dochadzka CHAR(9) NOT NULL DEFAULT '---------',
  UNIQUE (ucastnik, kruzok)
);

-->> LOGGING <<--

-- trigger after updates/deletes
CREATE TABLE kruzok_log (
  operacia CHAR(1) NOT NULL,
  datum TIMESTAMP NOT NULL,
  uzivatel INT REFERENCES uzivatel(id),
  udaje JSON 
);

CREATE OR REPLACE FUNCTION process_kruzok_log() RETURNS TRIGGER as
$$
  BEGIN
    IF (TG_OP = 'DELETE') THEN
      INSERT INTO kruzok_log SELECT 'D', now(), OLD.uzivatel, json_build_object('id', OLD.id, 'nazov', OLD.nazov, 'veduci', OLD.veduci, 'zadarmo', OLD.zadarmo);
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
CREATE TABLE ucastnik_log (
  operacia CHAR(1) NOT NULL,
  datum TIMESTAMP NOT NULL,
  uzivatel INT REFERENCES uzivatel(id),
  udaje JSON 
);

CREATE OR REPLACE FUNCTION process_ucastnik_log() RETURNS TRIGGER as
$$
  BEGIN
    IF (TG_OP = 'DELETE') THEN
      INSERT INTO ucastnik_log SELECT 'D', now(), OLD.uzivatel, json_build_object('id', OLD.id, 'cisloRozhodnutia', OLD.cislo_rozhodnutia, 'pohlavie', OLD.pohlavie, 'meno', OLD.meno, 'priezvisko', OLD.priezvisko, 'datumNarodenia', OLD.datum_narodenia, 'adresa', OLD.adresa);
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
