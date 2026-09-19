SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS countries (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code CHAR(2) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL, currency CHAR(3) NOT NULL DEFAULT 'EUR', is_active TINYINT(1) NOT NULL DEFAULT 1
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS economic_parameters (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, country_id INT UNSIGNED NOT NULL, section VARCHAR(50) NOT NULL, parameter_key VARCHAR(100) NOT NULL, label VARCHAR(150) NOT NULL, value DECIMAL(14,4) NOT NULL, unit VARCHAR(30) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_country_parameter(country_id,parameter_key), CONSTRAINT fk_parameter_country FOREIGN KEY(country_id) REFERENCES countries(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS economic_parameter_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, parameter_id BIGINT UNSIGNED NOT NULL, old_value DECIMAL(14,4) NOT NULL, new_value DECIMAL(14,4) NOT NULL,
 effective_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_history_parameter FOREIGN KEY(parameter_id) REFERENCES economic_parameters(id)
);
ALTER TABLE countries CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE economic_parameters CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE economic_parameter_history CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE economic_parameter_history ADD COLUMN IF NOT EXISTS effective_from DATETIME NULL AFTER new_value;
UPDATE economic_parameter_history SET effective_from=changed_at WHERE effective_from IS NULL;
CREATE TABLE IF NOT EXISTS banks (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 country_id INT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,
 code VARCHAR(30) NOT NULL UNIQUE,
 capital DECIMAL(16,2) NOT NULL DEFAULT 0,
 liquidity_index DECIMAL(8,2) NOT NULL DEFAULT 100,
 risk_appetite DECIMAL(8,2) NOT NULL DEFAULT 100,
 loan_margin DECIMAL(8,4) NOT NULL DEFAULT 2.5,
 deposit_margin DECIMAL(8,4) NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_bank_country FOREIGN KEY(country_id) REFERENCES countries(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS bank_loan_products (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 bank_id INT UNSIGNED NOT NULL,
 name VARCHAR(120) NOT NULL,
 min_amount DECIMAL(14,2) NOT NULL DEFAULT 1000,
 max_amount DECIMAL(14,2) NOT NULL DEFAULT 100000,
 max_term_months SMALLINT UNSIGNED NOT NULL DEFAULT 60,
 margin DECIMAL(8,4) NOT NULL DEFAULT 0,
 min_equity_percent DECIMAL(8,2) NOT NULL DEFAULT 20,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 CONSTRAINT fk_loan_product_bank FOREIGN KEY(bank_id) REFERENCES banks(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE banks CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE bank_loan_products CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS companies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 country_id INT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL,
 cash DECIMAL(16,2) NOT NULL DEFAULT 0,
 assets DECIMAL(16,2) NOT NULL DEFAULT 0,
 liabilities DECIMAL(16,2) NOT NULL DEFAULT 0,
 monthly_revenue DECIMAL(16,2) NOT NULL DEFAULT 0,
 monthly_profit DECIMAL(16,2) NOT NULL DEFAULT 0,
 age_months INT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_company_country FOREIGN KEY(country_id) REFERENCES countries(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS company_bank_accounts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 bank_id INT UNSIGNED NOT NULL,
 account_number VARCHAR(34) NOT NULL UNIQUE,
 currency CHAR(3) NOT NULL DEFAULT 'EUR',
 balance DECIMAL(16,2) NOT NULL DEFAULT 0,
 is_primary TINYINT(1) NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_account_company FOREIGN KEY(company_id) REFERENCES companies(id),
 CONSTRAINT fk_account_bank FOREIGN KEY(bank_id) REFERENCES banks(id),
 INDEX idx_account_company(company_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS bank_account_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 transaction_type VARCHAR(40) NOT NULL,
 amount DECIMAL(16,2) NOT NULL,
 balance_after DECIMAL(16,2) NOT NULL,
 reference_type VARCHAR(40) NULL,
 reference_id BIGINT UNSIGNED NULL,
 description VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_transaction_account FOREIGN KEY(account_id) REFERENCES company_bank_accounts(id),
 INDEX idx_transaction_account_date(account_id,created_at)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loan_applications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 bank_id INT UNSIGNED NOT NULL,
 loan_product_id INT UNSIGNED NULL,
 requested_amount DECIMAL(14,2) NOT NULL,
 requested_term_months SMALLINT UNSIGNED NOT NULL,
 equity_percent DECIMAL(8,2) NOT NULL,
 risk_score DECIMAL(8,2) NOT NULL,
 risk_margin DECIMAL(8,4) NOT NULL,
 offered_interest_rate DECIMAL(8,4) NOT NULL,
 status ENUM('pending','approved','rejected','accepted','cancelled') NOT NULL DEFAULT 'pending',
 decision_reason VARCHAR(255) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 decided_at DATETIME NULL,
 CONSTRAINT fk_loan_company FOREIGN KEY(company_id) REFERENCES companies(id),
 CONSTRAINT fk_loan_bank FOREIGN KEY(bank_id) REFERENCES banks(id),
 CONSTRAINT fk_loan_product FOREIGN KEY(loan_product_id) REFERENCES bank_loan_products(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS company_loans (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL UNIQUE,
 company_id BIGINT UNSIGNED NOT NULL,
 bank_id INT UNSIGNED NOT NULL,
 principal DECIMAL(14,2) NOT NULL,
 outstanding_principal DECIMAL(14,2) NOT NULL,
 annual_interest_rate DECIMAL(8,4) NOT NULL,
 term_months SMALLINT UNSIGNED NOT NULL,
 monthly_payment DECIMAL(14,2) NOT NULL,
 started_at DATETIME NOT NULL,
 next_payment_at DATETIME NOT NULL,
 status ENUM('active','paid','defaulted') NOT NULL DEFAULT 'active',
 CONSTRAINT fk_company_loan_application FOREIGN KEY(application_id) REFERENCES loan_applications(id),
 CONSTRAINT fk_company_loan_company FOREIGN KEY(company_id) REFERENCES companies(id),
 CONSTRAINT fk_company_loan_bank FOREIGN KEY(bank_id) REFERENCES banks(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loan_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 loan_id BIGINT UNSIGNED NOT NULL,
 installment_no SMALLINT UNSIGNED NOT NULL,
 due_at DATETIME NOT NULL,
 scheduled_amount DECIMAL(14,2) NOT NULL,
 interest_amount DECIMAL(14,2) NOT NULL,
 principal_amount DECIMAL(14,2) NOT NULL,
 penalty_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
 paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
 paid_at DATETIME NULL,
 status ENUM('waiting','due','late','paid') NOT NULL DEFAULT 'waiting',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_loan_installment(loan_id,installment_no),
 CONSTRAINT fk_payment_loan FOREIGN KEY(loan_id) REFERENCES company_loans(id),
 INDEX idx_payment_due(loan_id,due_at,status)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS company_registrations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL UNIQUE,
 registration_code VARCHAR(24) NOT NULL UNIQUE,
 status ENUM('active','suspended','closed') NOT NULL DEFAULT 'active',
 registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_registration_company FOREIGN KEY(company_id) REFERENCES companies(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS company_obligations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 institution VARCHAR(40) NOT NULL,
 obligation_type VARCHAR(60) NOT NULL,
 description VARCHAR(180) NOT NULL,
 amount DECIMAL(16,2) NOT NULL,
 due_at DATETIME NOT NULL,
 paid_at DATETIME NULL,
 penalty_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
 status ENUM('pending','late','paid') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_obligation_company FOREIGN KEY(company_id) REFERENCES companies(id),
 INDEX idx_obligation_due(company_id,due_at,status)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS company_utility_contracts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 utility_type ENUM('electricity','gas','water','sewerage') NOT NULL,
 monthly_usage DECIMAL(14,3) NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_company_utility(company_id,utility_type),
 CONSTRAINT fk_utility_company FOREIGN KEY(company_id) REFERENCES companies(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS company_properties (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 property_type ENUM('office','shop','warehouse','factory') NOT NULL,
 city VARCHAR(100) NOT NULL,
 area_m2 DECIMAL(10,2) NOT NULL,
 monthly_rent DECIMAL(14,2) NOT NULL,
 status ENUM('active','ended') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_property_company FOREIGN KEY(company_id) REFERENCES companies(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS company_employment (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 employees INT UNSIGNED NOT NULL DEFAULT 0,
 average_salary DECIMAL(14,2) NOT NULL DEFAULT 0,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_employment_company(company_id),
 CONSTRAINT fk_employment_company FOREIGN KEY(company_id) REFERENCES companies(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS company_trade_profiles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL UNIQUE,
 import_enabled TINYINT(1) NOT NULL DEFAULT 0,
 export_enabled TINYINT(1) NOT NULL DEFAULT 0,
 customs_debt DECIMAL(14,2) NOT NULL DEFAULT 0,
 CONSTRAINT fk_trade_company FOREIGN KEY(company_id) REFERENCES companies(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS game_clock (
 id TINYINT UNSIGNED PRIMARY KEY,
 game_date DATE NOT NULL,
 speed TINYINT UNSIGNED NOT NULL DEFAULT 1,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
INSERT INTO game_clock(id,game_date,speed) VALUES(1,CURDATE(),1) ON DUPLICATE KEY UPDATE id=id;

ALTER TABLE companies ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL AFTER name;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS industry VARCHAR(60) NULL AFTER city;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS starting_capital DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER industry;

CREATE TABLE IF NOT EXISTS property_market (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 city VARCHAR(100) NOT NULL,
 property_type ENUM('office','shop','warehouse','factory') NOT NULL,
 title VARCHAR(160) NOT NULL,
 area_m2 DECIMAL(10,2) NOT NULL,
 rent_per_m2 DECIMAL(10,2) NOT NULL,
 monthly_rent DECIMAL(14,2) NOT NULL,
 utility_factor DECIMAL(8,3) NOT NULL DEFAULT 1,
 is_available TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE company_properties ADD COLUMN IF NOT EXISTS market_property_id BIGINT UNSIGNED NULL AFTER company_id;

CREATE TABLE IF NOT EXISTS company_monthly_cycles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 period CHAR(7) NOT NULL,
 revenue DECIMAL(16,2) NOT NULL DEFAULT 0,
 rent_cost DECIMAL(16,2) NOT NULL DEFAULT 0,
 utility_cost DECIMAL(16,2) NOT NULL DEFAULT 0,
 payroll_cost DECIMAL(16,2) NOT NULL DEFAULT 0,
 payroll_tax DECIMAL(16,2) NOT NULL DEFAULT 0,
 vat_due DECIMAL(16,2) NOT NULL DEFAULT 0,
 profit_tax_due DECIMAL(16,2) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_company_period(company_id,period),
 CONSTRAINT fk_cycle_company FOREIGN KEY(company_id) REFERENCES companies(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS market_demand (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 city VARCHAR(100) NOT NULL,
 industry VARCHAR(60) NOT NULL,
 demand_index DECIMAL(10,2) NOT NULL DEFAULT 100,
 purchasing_power DECIMAL(10,2) NOT NULL DEFAULT 100,
 population INT UNSIGNED NOT NULL DEFAULT 0,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_market_city_industry(city,industry)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO property_market(city,property_type,title,area_m2,rent_per_m2,monthly_rent,utility_factor)
SELECT 'Vilnius','office','Centro biuras',80,9.50,760,0.80 WHERE NOT EXISTS(SELECT 1 FROM property_market);
INSERT INTO property_market(city,property_type,title,area_m2,rent_per_m2,monthly_rent,utility_factor)
SELECT 'Vilnius','shop','Prekybinės patalpos',140,9.50,1330,1.10 WHERE (SELECT COUNT(*) FROM property_market)<2;
INSERT INTO property_market(city,property_type,title,area_m2,rent_per_m2,monthly_rent,utility_factor)
SELECT 'Kaunas','warehouse','Logistikos sandėlis',500,6.00,3000,0.65 WHERE (SELECT COUNT(*) FROM property_market)<3;
INSERT INTO property_market(city,property_type,title,area_m2,rent_per_m2,monthly_rent,utility_factor)
SELECT 'Klaipėda','factory','Gamybinės patalpos',900,7.00,6300,1.80 WHERE (SELECT COUNT(*) FROM property_market)<4;


ALTER TABLE companies ADD COLUMN IF NOT EXISTS company_type ENUM('player','ai') NOT NULL DEFAULT 'player' AFTER starting_capital;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS status ENUM('active','insolvent','bankrupt') NOT NULL DEFAULT 'active' AFTER company_type;
ALTER TABLE companies ADD COLUMN IF NOT EXISTS credit_score SMALLINT UNSIGNED NOT NULL DEFAULT 700 AFTER status;

CREATE TABLE IF NOT EXISTS ai_company_profiles (
 company_id BIGINT UNSIGNED PRIMARY KEY,
 strategy ENUM('cautious','balanced','growth') NOT NULL DEFAULT 'balanced',
 target_employees INT UNSIGNED NOT NULL DEFAULT 3,
 price_index DECIMAL(8,2) NOT NULL DEFAULT 100,
 marketing_index DECIMAL(8,2) NOT NULL DEFAULT 100,
 months_in_loss SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 CONSTRAINT fk_ai_company FOREIGN KEY(company_id) REFERENCES companies(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS economy_snapshots (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 period CHAR(7) NOT NULL UNIQUE,
 inflation DECIMAL(10,4) NOT NULL,
 gdp_growth DECIMAL(10,4) NOT NULL,
 unemployment DECIMAL(10,4) NOT NULL,
 consumer_confidence DECIMAL(10,4) NOT NULL,
 business_confidence DECIMAL(10,4) NOT NULL,
 purchasing_power DECIMAL(10,4) NOT NULL,
 property_index DECIMAL(10,4) NOT NULL,
 active_companies INT UNSIGNED NOT NULL DEFAULT 0,
 bankruptcies INT UNSIGNED NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO market_demand(city,industry,demand_index,purchasing_power,population) VALUES
('Vilnius','retail',112,108,607000),('Vilnius','services',110,108,607000),('Vilnius','logistics',104,108,607000),('Vilnius','manufacturing',101,108,607000),
('Kaunas','retail',105,102,304000),('Kaunas','services',104,102,304000),('Kaunas','logistics',108,102,304000),('Kaunas','manufacturing',105,102,304000),
('Klaipėda','retail',101,100,160000),('Klaipėda','services',100,100,160000),('Klaipėda','logistics',112,100,160000),('Klaipėda','manufacturing',106,100,160000),
('Šiauliai','retail',96,94,112000),('Šiauliai','services',95,94,112000),('Šiauliai','logistics',99,94,112000),('Šiauliai','manufacturing',101,94,112000),
('Panevėžys','retail',95,93,89000),('Panevėžys','services',94,93,89000),('Panevėžys','logistics',100,93,89000),('Panevėžys','manufacturing',102,93,89000)
ON DUPLICATE KEY UPDATE population=VALUES(population);

INSERT INTO countries(code,name,currency) VALUES('LT','Lietuva','EUR') ON DUPLICATE KEY UPDATE name=VALUES(name),currency=VALUES(currency);
SET @lt=(SELECT id FROM countries WHERE code='LT');
DELETE FROM economic_parameters WHERE country_id=@lt AND section='banking' AND parameter_key='business_loan';
UPDATE company_bank_accounts a JOIN banks b ON b.id=a.bank_id
SET a.account_number=CONCAT('LT',LPAD(10+(a.company_id MOD 89),2,'0'),LEFT(UPPER(b.code),2),LPAD(a.bank_id,4,'0'),LPAD(a.company_id,8,'0'))
WHERE CHAR_LENGTH(a.account_number)>18;

CREATE TABLE IF NOT EXISTS state_institutions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 country_id INT UNSIGNED NOT NULL,
 code VARCHAR(40) NOT NULL UNIQUE,
 name VARCHAR(160) NOT NULL,
 institution_type ENUM('tax','social','property','utility','customs','treasury') NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 CONSTRAINT fk_state_institution_country FOREIGN KEY(country_id) REFERENCES countries(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS state_bank_accounts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 institution_id INT UNSIGNED NOT NULL,
 bank_id INT UNSIGNED NOT NULL,
 account_number VARCHAR(34) NOT NULL UNIQUE,
 currency CHAR(3) NOT NULL DEFAULT 'EUR',
 balance DECIMAL(18,2) NOT NULL DEFAULT 0,
 is_primary TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_state_account_institution FOREIGN KEY(institution_id) REFERENCES state_institutions(id),
 CONSTRAINT fk_state_account_bank FOREIGN KEY(bank_id) REFERENCES banks(id),
 UNIQUE KEY uq_state_institution_bank(institution_id,bank_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS state_bank_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 company_id BIGINT UNSIGNED NULL,
 transaction_type VARCHAR(50) NOT NULL,
 amount DECIMAL(18,2) NOT NULL,
 balance_after DECIMAL(18,2) NOT NULL,
 reference_type VARCHAR(50) NULL,
 reference_id BIGINT UNSIGNED NULL,
 description VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_state_transaction_account FOREIGN KEY(account_id) REFERENCES state_bank_accounts(id),
 CONSTRAINT fk_state_transaction_company FOREIGN KEY(company_id) REFERENCES companies(id),
 INDEX idx_state_transaction_account_date(account_id,created_at)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE company_obligations ADD COLUMN IF NOT EXISTS institution_id INT UNSIGNED NULL AFTER institution;
ALTER TABLE company_obligations ADD COLUMN IF NOT EXISTS recipient_account_id BIGINT UNSIGNED NULL AFTER institution_id;

SET @lt_state=(SELECT id FROM countries WHERE code='LT' LIMIT 1);
INSERT INTO state_institutions(country_id,code,name,institution_type) VALUES
(@lt_state,'VMI','Valstybinė mokesčių inspekcija','tax'),
(@lt_state,'SODRA','Valstybinio socialinio draudimo fondo valdyba','social'),
(@lt_state,'NT','Valstybės turto ir nekilnojamojo turto administracija','property'),
(@lt_state,'UTILITIES','Valstybinis energijos ir komunalinių paslaugų centras','utility'),
(@lt_state,'CUSTOMS','Lietuvos muitinė','customs'),
(@lt_state,'TREASURY','Lietuvos Respublikos valstybės iždas','treasury')
ON DUPLICATE KEY UPDATE name=VALUES(name),institution_type=VALUES(institution_type),is_active=1;

INSERT INTO state_bank_accounts(institution_id,bank_id,account_number,currency,balance,is_primary)
SELECT si.id,b.id,
 CONCAT('LT90',LEFT(UPPER(b.code),2),LPAD(b.id,4,'0'),LPAD(si.id,8,'0')),
 'EUR',0,1
FROM state_institutions si
JOIN banks b ON b.country_id=si.country_id AND b.code='VB'
WHERE NOT EXISTS(SELECT 1 FROM state_bank_accounts sba WHERE sba.institution_id=si.id);

INSERT INTO economic_parameters(country_id,section,parameter_key,label,value,unit) VALUES
(@lt,'economy','inflation','Infliacija',2.8,'%'),
(@lt,'economy','gdp_growth','BVP augimas',2.0,'%'),
(@lt,'economy','consumer_confidence','Vartotojų pasitikėjimas',100,'ind.'),
(@lt,'economy','business_confidence','Verslo pasitikėjimas',100,'ind.'),
(@lt,'energy','electricity','Elektros bazinė kaina',0.21,'€/kWh'),
(@lt,'energy','gas','Dujų bazinė kaina',0.09,'€/kWh'),
(@lt,'energy','electricity_capacity','Elektros tiekimo pajėgumas',100,'ind.'),
(@lt,'energy','gas_capacity','Dujų tiekimo pajėgumas',100,'ind.'),
(@lt,'energy','price_volatility','Leistinas kainos svyravimas',10,'%'),
(@lt,'energy','supply_stability','Tiekimo stabilumas',100,'%'),
(@lt,'energy','automatic_pricing','Automatinis kainų valdymas',0,'0/1'),
(@lt,'water','water','Vandens bazinė kaina',1.20,'€/m³'),
(@lt,'water','sewerage','Nuotekų bazinė kaina',1.45,'€/m³'),
(@lt,'water','water_capacity','Vandens tiekimo pajėgumas',100,'ind.'),
(@lt,'water','sewerage_capacity','Nuotekų sistemos pajėgumas',100,'ind.'),
(@lt,'water','water_stability','Tiekimo stabilumas',100,'%'),
(@lt,'water','water_price_volatility','Leistinas kainos svyravimas',5,'%'),
(@lt,'water','automatic_water_pricing','Automatinis kainų valdymas',0,'0/1'),
(@lt,'banking','base_rate','Bazinė palūkanų norma',3.5,'%'),
(@lt,'banking','deposit_rate','Indėlių palūkanos',2.0,'%'),
(@lt,'banking','max_loan_term','Maksimalus verslo paskolos terminas',10,'metai'),
(@lt,'banking','min_equity','Minimalus nuosavas kapitalas paskolai',20,'%'),
(@lt,'banking','credit_availability','Kreditavimo prieinamumas',100,'ind.'),
(@lt,'taxes','vat','PVM',21,'%'),
(@lt,'taxes','profit_tax','Pelno mokestis',17,'%'),
(@lt,'taxes','dividend_tax','Dividendų mokestis',15,'%'),
(@lt,'taxes','payroll_burden','Darbo mokesčių našta',20,'%'),
(@lt,'taxes','property_tax','Komercinio NT mokestis',1,'%'),
(@lt,'transport','diesel','Dyzelino bazinė kaina',1.62,'€/l'),
(@lt,'transport','petrol','Benzino bazinė kaina',1.55,'€/l'),
(@lt,'transport','road_cost_index','Kelių transporto kaštai',100,'ind.'),
(@lt,'transport','rail_cost_index','Geležinkelių transporto kaštai',100,'ind.'),
(@lt,'transport','sea_cost_index','Jūrų transporto kaštai',100,'ind.'),
(@lt,'transport','transport_capacity','Transporto pajėgumas',100,'ind.'),
(@lt,'labour','minimum_wage','Minimalus atlyginimas',1250,'€'),
(@lt,'labour','average_wage','Vidutinis atlyginimas',2300,'€'),
(@lt,'labour','unemployment','Nedarbo lygis',7,'%'),
(@lt,'labour','labour_supply','Darbo jėgos pasiūla',100,'ind.'),
(@lt,'labour','productivity','Darbo našumas',100,'ind.'),
(@lt,'real_estate','commercial_rent','Komercinė nuoma',9.5,'€/m²'),
(@lt,'real_estate','warehouse_rent','Sandėlių nuoma',6.0,'€/m²'),
(@lt,'real_estate','industrial_rent','Gamybinių patalpų nuoma',7.0,'€/m²'),
(@lt,'real_estate','property_price_index','NT kainų indeksas',100,'ind.'),
(@lt,'real_estate','vacancy_rate','Laisvų komercinių patalpų dalis',8,'%'),
(@lt,'trade','import_index','Importo kaštų indeksas',100,'ind.'),
(@lt,'trade','export_index','Eksporto kaštų indeksas',100,'ind.'),
(@lt,'trade','customs_rate','Bendras muito tarifas',0,'%'),
(@lt,'trade','border_efficiency','Sienų ir muitinės efektyvumas',100,'ind.'),
(@lt,'population','population_total','Gyventojų skaičius',2890000,'žm.'),
(@lt,'population','purchasing_power','Perkamosios galios indeksas',100,'ind.'),
(@lt,'population','population_growth','Gyventojų pokytis',0,'%'),
(@lt,'population','working_age_share','Darbingo amžiaus gyventojai',64,'%'),
(@lt,'population','urbanisation','Urbanizacija',68,'%')
ON DUPLICATE KEY UPDATE label=VALUES(label),unit=VALUES(unit);

SET @lt=(SELECT id FROM countries WHERE code='LT');
INSERT INTO banks(country_id,name,code,capital,liquidity_index,risk_appetite,loan_margin,deposit_margin,is_active) VALUES
(@lt,'Verslo Bankas','VB',250000000,100,100,2.20,0.20,1),
(@lt,'Kapitalo Bankas','KB',180000000,95,85,2.80,0.35,1),
(@lt,'Augimo Bankas','AB',120000000,105,120,3.10,0.10,1)
ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO bank_loan_products(bank_id,name,min_amount,max_amount,max_term_months,margin,min_equity_percent,is_active)
SELECT b.id,'Verslo paskola',5000,250000,120,0,20,1 FROM banks b
WHERE b.country_id=@lt AND NOT EXISTS(SELECT 1 FROM bank_loan_products p WHERE p.bank_id=b.id AND p.name='Verslo paskola');


CREATE TABLE IF NOT EXISTS job_roles (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(50) NOT NULL UNIQUE,
 name VARCHAR(100) NOT NULL,
 base_salary DECIMAL(14,2) NOT NULL,
 productivity DECIMAL(8,2) NOT NULL DEFAULT 100,
 automation_type VARCHAR(50) NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS company_employees (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 role_id INT UNSIGNED NOT NULL,
 full_name VARCHAR(120) NOT NULL,
 salary DECIMAL(14,2) NOT NULL,
 skill DECIMAL(8,2) NOT NULL DEFAULT 70,
 status ENUM('active','dismissed') NOT NULL DEFAULT 'active',
 hired_at DATE NOT NULL,
 CONSTRAINT fk_employee_company FOREIGN KEY(company_id) REFERENCES companies(id),
 CONSTRAINT fk_employee_role FOREIGN KEY(role_id) REFERENCES job_roles(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sku VARCHAR(50) NOT NULL UNIQUE,
 name VARCHAR(160) NOT NULL,
 industry VARCHAR(60) NOT NULL,
 base_cost DECIMAL(14,2) NOT NULL,
 base_price DECIMAL(14,2) NOT NULL,
 unit_volume DECIMAL(10,3) NOT NULL DEFAULT 1
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS company_inventory (
 company_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(14,2) NOT NULL DEFAULT 0,
 average_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
 sale_price DECIMAL(14,2) NOT NULL DEFAULT 0,
 PRIMARY KEY(company_id,product_id),
 CONSTRAINT fk_inventory_company FOREIGN KEY(company_id) REFERENCES companies(id),
 CONSTRAINT fk_inventory_product FOREIGN KEY(product_id) REFERENCES products(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS suppliers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 country_code CHAR(2) NOT NULL,
 industry VARCHAR(60) NOT NULL,
 price_factor DECIMAL(8,3) NOT NULL DEFAULT 1,
 delivery_days SMALLINT UNSIGNED NOT NULL DEFAULT 3,
 is_active TINYINT(1) NOT NULL DEFAULT 1
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS purchase_orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 supplier_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 quantity DECIMAL(14,2) NOT NULL,
 unit_cost DECIMAL(14,2) NOT NULL,
 transport_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
 customs_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
 total_cost DECIMAL(16,2) NOT NULL,
 ordered_at DATE NOT NULL,
 arrives_at DATE NOT NULL,
 status ENUM('ordered','delivered','cancelled') NOT NULL DEFAULT 'ordered',
 CONSTRAINT fk_po_company FOREIGN KEY(company_id) REFERENCES companies(id),
 CONSTRAINT fk_po_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id),
 CONSTRAINT fk_po_product FOREIGN KEY(product_id) REFERENCES products(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS company_marketing (
 company_id BIGINT UNSIGNED PRIMARY KEY,
 monthly_budget DECIMAL(14,2) NOT NULL DEFAULT 0,
 brand_awareness DECIMAL(8,2) NOT NULL DEFAULT 50,
 reputation DECIMAL(8,2) NOT NULL DEFAULT 70,
 CONSTRAINT fk_marketing_company FOREIGN KEY(company_id) REFERENCES companies(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sales_ledger (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 period CHAR(7) NOT NULL,
 quantity DECIMAL(14,2) NOT NULL DEFAULT 0,
 revenue DECIMAL(16,2) NOT NULL DEFAULT 0,
 cost DECIMAL(16,2) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_sales_company FOREIGN KEY(company_id) REFERENCES companies(id),
 CONSTRAINT fk_sales_product FOREIGN KEY(product_id) REFERENCES products(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS state_expenses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 institution_id INT UNSIGNED NOT NULL,
 recipient_company_id BIGINT UNSIGNED NULL,
 expense_type VARCHAR(60) NOT NULL,
 description VARCHAR(200) NOT NULL,
 amount DECIMAL(16,2) NOT NULL,
 paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_expense_institution FOREIGN KEY(institution_id) REFERENCES state_institutions(id),
 CONSTRAINT fk_expense_company FOREIGN KEY(recipient_company_id) REFERENCES companies(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO job_roles(code,name,base_salary,productivity,automation_type) VALUES
('seller','Pardavėjas',1600,100,NULL),('warehouse','Sandėlininkas',1700,100,NULL),('driver','Vairuotojas',2100,100,NULL),('accountant','Buhalteris',2300,100,'accounting'),('manager','Vadybininkas',2400,110,NULL),('marketing','Marketingo specialistas',2300,105,NULL),('production','Gamybos darbuotojas',1800,100,NULL)
ON DUPLICATE KEY UPDATE name=VALUES(name),base_salary=VALUES(base_salary),productivity=VALUES(productivity),automation_type=VALUES(automation_type);
INSERT INTO products(sku,name,industry,base_cost,base_price) VALUES
('RET-001','Kasdienė prekė','retail',5,9),('LOG-001','Logistikos paslauga','logistics',40,75),('MAN-001','Gamybos produktas','manufacturing',20,38),('SRV-001','Verslo paslauga','services',30,60)
ON DUPLICATE KEY UPDATE name=VALUES(name),base_cost=VALUES(base_cost),base_price=VALUES(base_price);
INSERT INTO suppliers(name,country_code,industry,price_factor,delivery_days)
SELECT 'Baltic Supply','LT','retail',1,2 WHERE NOT EXISTS(SELECT 1 FROM suppliers WHERE name='Baltic Supply');
INSERT INTO suppliers(name,country_code,industry,price_factor,delivery_days)
SELECT 'Euro Wholesale','DE','retail',0.88,6 WHERE NOT EXISTS(SELECT 1 FROM suppliers WHERE name='Euro Wholesale');


ALTER TABLE property_market ADD COLUMN IF NOT EXISTS owner_type ENUM('state','company') NOT NULL DEFAULT 'state' AFTER id;
ALTER TABLE property_market ADD COLUMN IF NOT EXISTS owner_company_id BIGINT UNSIGNED NULL AFTER owner_type;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS supplier_type ENUM('domestic','foreign') NOT NULL DEFAULT 'domestic' AFTER id;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS company_id BIGINT UNSIGNED NULL AFTER supplier_type;
ALTER TABLE company_monthly_cycles ADD COLUMN IF NOT EXISTS stock_cost DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER revenue;
ALTER TABLE company_monthly_cycles ADD COLUMN IF NOT EXISTS marketing_cost DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER utility_cost;
ALTER TABLE company_monthly_cycles ADD COLUMN IF NOT EXISTS input_vat DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER vat_due;
ALTER TABLE company_monthly_cycles ADD COLUMN IF NOT EXISTS taxable_result DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER input_vat;

CREATE TABLE IF NOT EXISTS economy_sectors (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(40) NOT NULL UNIQUE,
 name VARCHAR(120) NOT NULL,
 balance DECIMAL(18,2) NOT NULL DEFAULT 0,
 sector_type ENUM('households','foreign') NOT NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS economy_sector_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 sector_id INT UNSIGNED NOT NULL,
 company_id BIGINT UNSIGNED NULL,
 transaction_type VARCHAR(50) NOT NULL,
 amount DECIMAL(18,2) NOT NULL,
 balance_after DECIMAL(18,2) NOT NULL,
 description VARCHAR(255) NOT NULL,
 game_date DATE NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_sector_tx_sector FOREIGN KEY(sector_id) REFERENCES economy_sectors(id),
 CONSTRAINT fk_sector_tx_company FOREIGN KEY(company_id) REFERENCES companies(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS company_tax_accounts (
 company_id BIGINT UNSIGNED PRIMARY KEY,
 vat_credit DECIMAL(16,2) NOT NULL DEFAULT 0,
 tax_loss_carryforward DECIMAL(16,2) NOT NULL DEFAULT 0,
 CONSTRAINT fk_tax_account_company FOREIGN KEY(company_id) REFERENCES companies(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS payroll_ledger (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 employee_id BIGINT UNSIGNED NULL,
 period CHAR(7) NOT NULL,
 gross_amount DECIMAL(16,2) NOT NULL,
 status ENUM('due','paid') NOT NULL DEFAULT 'due',
 paid_at DATE NULL,
 CONSTRAINT fk_payroll_company FOREIGN KEY(company_id) REFERENCES companies(id),
 CONSTRAINT fk_payroll_employee FOREIGN KEY(employee_id) REFERENCES company_employees(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
INSERT INTO economy_sectors(code,name,balance,sector_type) VALUES
('HOUSEHOLDS','Gyventojų sektorius',2500000000,'households'),
('FOREIGN','Užsienio sektorius',5000000000,'foreign')
ON DUPLICATE KEY UPDATE name=VALUES(name);
UPDATE suppliers SET supplier_type=IF(country_code='LT','domestic','foreign');


ALTER TABLE company_utility_contracts ADD COLUMN IF NOT EXISTS provider_type ENUM('state','company') NOT NULL DEFAULT 'state';
ALTER TABLE company_utility_contracts ADD COLUMN IF NOT EXISTS provider_company_id BIGINT UNSIGNED NULL;
ALTER TABLE payroll_ledger ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER gross_amount;
ALTER TABLE payroll_ledger ADD COLUMN IF NOT EXISTS net_amount DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER tax_amount;
ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS input_vat DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER total_cost;
ALTER TABLE state_expenses ADD COLUMN IF NOT EXISTS recipient_type ENUM('company','households','foreign') NOT NULL DEFAULT 'company' AFTER institution_id;
ALTER TABLE state_expenses ADD COLUMN IF NOT EXISTS game_date DATE NULL AFTER amount;
CREATE TABLE IF NOT EXISTS company_payment_arrears (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 company_id BIGINT UNSIGNED NOT NULL,
 arrears_type ENUM('payroll','rent','utilities','marketing') NOT NULL,
 reference_id BIGINT UNSIGNED NULL,
 period CHAR(7) NOT NULL,
 amount DECIMAL(16,2) NOT NULL,
 status ENUM('due','paid') NOT NULL DEFAULT 'due',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 paid_at DATETIME NULL,
 CONSTRAINT fk_arrears_company FOREIGN KEY(company_id) REFERENCES companies(id),
 INDEX idx_arrears_company(company_id,status)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


ALTER TABLE game_clock ADD COLUMN IF NOT EXISTS test_offset_days INT NOT NULL DEFAULT 0;
ALTER TABLE game_clock ADD COLUMN IF NOT EXISTS last_processed_date DATE NULL;
UPDATE game_clock SET game_date=CURRENT_DATE, test_offset_days=0, last_processed_date=CURRENT_DATE WHERE id=1;
ALTER TABLE bank_account_transactions ADD COLUMN IF NOT EXISTS game_date DATE NULL;
ALTER TABLE state_bank_transactions ADD COLUMN IF NOT EXISTS game_date DATE NULL;
UPDATE bank_account_transactions SET game_date=DATE(created_at) WHERE game_date IS NULL;
UPDATE state_bank_transactions SET game_date=DATE(created_at) WHERE game_date IS NULL;


ALTER TABLE economy_sectors MODIFY COLUMN sector_type ENUM('households','foreign','business') NOT NULL;
INSERT INTO economy_sectors(code,name,balance,sector_type) VALUES
('DOMESTIC_BUSINESS','Lietuvos verslo tiekėjų sektorius',250000000,'business')
ON DUPLICATE KEY UPDATE name=VALUES(name),sector_type=VALUES(sector_type);

INSERT INTO suppliers(name,country_code,industry,price_factor,delivery_days)
SELECT 'Lietuvos logistikos partneriai','LT','logistics',1,2 WHERE NOT EXISTS(SELECT 1 FROM suppliers WHERE name='Lietuvos logistikos partneriai');
INSERT INTO suppliers(name,country_code,industry,price_factor,delivery_days)
SELECT 'Baltic Industrial Supply','LT','manufacturing',1,3 WHERE NOT EXISTS(SELECT 1 FROM suppliers WHERE name='Baltic Industrial Supply');
INSERT INTO suppliers(name,country_code,industry,price_factor,delivery_days)
SELECT 'Verslo paslaugų tinklas','LT','services',1,1 WHERE NOT EXISTS(SELECT 1 FROM suppliers WHERE name='Verslo paslaugų tinklas');
UPDATE suppliers SET supplier_type=IF(country_code='LT','domestic','foreign');
