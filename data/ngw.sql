-- Table: alleles
CREATE TABLE alleles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    value REAL,
    dominance REAL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
, additive INTEGER DEFAULT 0, epistasis TEXT DEFAULT '')
;
INSERT INTO "alleles" (id,name,value,dominance,created_at,additive,epistasis) VALUES(2,'Amarillo',2.0,100.0,'1970-01-01 00:00:00',0,'');
INSERT INTO "alleles" (id,name,value,dominance,created_at,additive,epistasis) VALUES(3,'verde',1.0,0.0,'1970-01-01 00:00:00',0,'');
INSERT INTO "alleles" (id,name,value,dominance,created_at,additive,epistasis) VALUES(6,'B1',50.0,100.0,'1970-01-01 00:00:00',0,'');
INSERT INTO "alleles" (id,name,value,dominance,created_at,additive,epistasis) VALUES(7,'B2',20.0,0.0,'1970-01-01 00:00:00',0,'');

-- Table: character_genes
CREATE TABLE character_genes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    character_id INTEGER NOT NULL,
    gene_id INTEGER NOT NULL,
    UNIQUE(character_id, gene_id),
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (gene_id) REFERENCES genes(id) ON DELETE CASCADE
)
;
INSERT INTO "character_genes" (id,character_id,gene_id) VALUES(1,2,1);
INSERT INTO "character_genes" (id,character_id,gene_id) VALUES(15,8,15);

-- Table: characters
CREATE TABLE characters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    creator_id INTEGER NOT NULL,
    is_public INTEGER DEFAULT 0,
    is_visible INTEGER DEFAULT 0,
    sex INTEGER DEFAULT 0,
    substrates INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
)
;
INSERT INTO "characters" (id,name,creator_id,is_public,is_visible,sex,substrates,created_at,updated_at) VALUES(2,'color',5,0,0,0,2,'1970-01-01 00:00:00','1970-01-01 00:00:00');
INSERT INTO "characters" (id,name,creator_id,is_public,is_visible,sex,substrates,created_at,updated_at) VALUES(8,'altura',5,0,1,0,0,'1970-01-01 00:00:00','1970-01-01 00:00:00');

-- Table: connections
CREATE TABLE connections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    character_id INTEGER NOT NULL,
    state_a INTEGER NOT NULL,
    transition INTEGER NOT NULL,
    state_b INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
)
;
INSERT INTO "connections" (id,character_id,state_a,transition,state_b,created_at) VALUES(5,2,0,1,1,'1970-01-01 00:00:00');

-- Table: gene_alleles
CREATE TABLE gene_alleles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    gene_id INTEGER NOT NULL,
    allele_id INTEGER NOT NULL,
    UNIQUE(gene_id, allele_id),
    FOREIGN KEY (gene_id) REFERENCES genes(id) ON DELETE CASCADE,
    FOREIGN KEY (allele_id) REFERENCES alleles(id) ON DELETE CASCADE
)
;
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(2,1,2);
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(3,1,3);

-- Table: generations
CREATE TABLE generations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    generation_number INTEGER NOT NULL,
    population_size INTEGER,
    type TEXT, -- 'random', 'cross'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, generation_number),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
)
;
INSERT INTO "generations" (id,project_id,generation_number,population_size,type,created_at) VALUES(10,6,1,100,'random','1970-01-01 00:00:00');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type,created_at) VALUES(31,6,2,50,'cross','1970-01-01 00:00:00');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type,created_at) VALUES(32,6,3,50,'cross','1970-01-01 00:00:00');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type,created_at) VALUES(33,6,4,50,'cross','1970-01-01 00:00:00');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type,created_at) VALUES(34,6,5,50,'cross','1970-01-01 00:00:00');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type,created_at) VALUES(35,6,6,50,'cross','1970-01-01 00:00:00');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type,created_at) VALUES(36,6,7,50,'cross','1970-01-01 00:00:00');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type,created_at) VALUES(37,6,8,50,'cross','1970-01-01 00:00:00');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type,created_at) VALUES(38,6,9,50,'cross','1970-01-01 00:00:00');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type,created_at) VALUES(39,6,10,50,'cross','1970-01-01 00:00:00');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type,created_at) VALUES(40,6,11,50,'cross','1970-01-01 00:00:00');

-- Table: genes
CREATE TABLE genes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    chromosome TEXT,
    position TEXT,
    code TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
;
INSERT INTO "genes" (id,name,chromosome,position,code,created_at) VALUES(1,'A','1 (A, B)','1','','1970-01-01 00:00:00');
INSERT INTO "genes" (id,name,chromosome,position,code,created_at) VALUES(15,'A','1','1','AB','1970-01-01 00:00:00');

-- Table: parentals
CREATE TABLE parentals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    generation_number INTEGER NOT NULL,
    individual_id INTEGER NOT NULL,
    parent_generation_number INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
)
;
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(101,6,2,12,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(102,6,2,14,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(103,6,2,15,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(104,6,2,19,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(105,6,3,44,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(106,6,3,45,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(107,6,3,46,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(108,6,3,48,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(109,6,4,1,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(110,6,4,2,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(111,6,4,4,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(112,6,4,5,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(113,6,5,84,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(114,6,5,85,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(115,6,5,86,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(116,6,5,87,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(117,6,6,9,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(118,6,6,10,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(119,6,6,11,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(120,6,6,13,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(121,6,7,34,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(122,6,7,35,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(123,6,7,36,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(124,6,7,38,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(125,6,8,77,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(126,6,8,79,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(127,6,8,80,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(128,6,8,81,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(129,6,9,47,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(130,6,9,51,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(131,6,9,54,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(132,6,9,56,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(133,6,10,26,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(134,6,10,28,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(135,6,10,29,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(136,6,10,30,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(137,6,11,62,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(138,6,11,64,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(139,6,11,65,1,'1970-01-01 00:00:00');
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number,created_at) VALUES(140,6,11,68,1,'1970-01-01 00:00:00');

-- Table: project_characters
CREATE TABLE project_characters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    character_id INTEGER NOT NULL,
    environment INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, character_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
)
;
INSERT INTO "project_characters" (id,project_id,character_id,environment,created_at) VALUES(1,6,2,0,'1970-01-01 00:00:00');

-- Table: projects
CREATE TABLE projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
;
INSERT INTO "projects" (id,name,description,user_id,created_at,updated_at) VALUES(6,'simple','',5,'1970-01-01 00:00:00','1970-01-01 00:00:00');

-- Table: registration_requests
CREATE TABLE registration_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    email TEXT,
    reason TEXT,
    status TEXT DEFAULT 'pending', -- pending, approved, rejected
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    processed_by INTEGER, password TEXT,
    FOREIGN KEY (processed_by) REFERENCES users(id)
)
;
INSERT INTO "registration_requests" (id,username,email,reason,status,requested_at,processed_at,processed_by,password) VALUES(1,'mburgos','mburgos@go.ugr.es','Nueva interfaz','approved','1970-01-01 00:00:00','1970-01-01 00:00:00',1,NULL);
INSERT INTO "registration_requests" (id,username,email,reason,status,requested_at,processed_at,processed_by,password) VALUES(2,'mburgos','','','approved','1970-01-01 00:00:00','1970-01-01 00:00:00',1,'$2y$12$VlyvHBsH16FE41b.DACW9.vlkdv5/PgAxa1K3HNbGeCBjGvx8NjTi');
INSERT INTO "registration_requests" (id,username,email,reason,status,requested_at,processed_at,processed_by,password) VALUES(3,'mburgos','','','approved','1970-01-01 00:00:00','1970-01-01 00:00:00',1,'$2y$12$ZzHSofb0FuMkZtwAzCxYCuwImnnuhaKCLzy3jzyBBs6JRfL.RuTHa');
INSERT INTO "registration_requests" (id,username,email,reason,status,requested_at,processed_at,processed_by,password) VALUES(4,'mburgos','','','approved','1970-01-01 00:00:00','1970-01-01 00:00:00',1,'$2y$12$1kXBdGhdnJaZsrCFLDiwl.TA8X17glEZRJLql1S9kMC.adE0BcbHy');

-- Table: users
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    email TEXT,
    is_admin INTEGER DEFAULT 0,
    is_approved INTEGER DEFAULT 0,
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
;
INSERT INTO "users" (id,username,password,email,is_admin,is_approved,requested_at,approved_at,created_at,updated_at) VALUES(1,'admin','$2y$12$EBhHhO8caCDeMnWjfYyBK.6iDdxbnCKdmPGn8sfZLeTq4tGFfhSpq','admin@ngw.local',1,1,'1970-01-01 00:00:00',NULL,'1970-01-01 00:00:00','1970-01-01 00:00:00');
INSERT INTO "users" (id,username,password,email,is_admin,is_approved,requested_at,approved_at,created_at,updated_at) VALUES(5,'mburgos','$2y$12$1kXBdGhdnJaZsrCFLDiwl.TA8X17glEZRJLql1S9kMC.adE0BcbHy','',0,1,'1970-01-01 00:00:00','1970-01-01 00:00:00','1970-01-01 00:00:00','1970-01-01 00:00:00');

