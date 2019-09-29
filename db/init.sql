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
  veduci INT REFERENCES uzivatel(id),
  UNIQUE (nazov)
);

CREATE TYPE pohlavie AS ENUM ('M', 'Z');
CREATE TABLE ucastnik (
  id SERIAL PRIMARY KEY,
  cislo_roznodnutia INT NOT NULL,
  pohlavie pohlavie NOT NULL,
  meno TEXT NOT NULL,
  priezvisko TEXT NOT NULL,
  datum_narodenia DATE NOT NULL,
  mesto_obec TEXT NOT NULL,
  ulica_cislo TEXT NOT NULL,
  kruzky INT ARRAY,
  UNIQUE (cislo_roznodnutia),
  UNIQUE (meno, priezvisko, datum_narodenia)
);
