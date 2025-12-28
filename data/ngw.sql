-- Table: alleles
CREATE TABLE alleles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    value REAL,
    dominance REAL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
, additive INTEGER DEFAULT 0, epistasis TEXT DEFAULT '')
;
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(2,'Amarillo',2.0,100.0,0,'');
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(3,'verde',1.0,0.0,0,'');
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(9,'TestAllele1766844065',1.23,1100.0,1,NULL);
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(23,'A1',50.0,1100.0,1,'');
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(24,'A2',20.0,1100.0,1,'');
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(28,'B1',50.0,1100.0,1,'');
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(29,'B2',20.0,1100.0,1,'');
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(30,'a1',1.0,100.0,0,'');
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(31,'b1',3.0,100.0,0,'');
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(32,'c1',3.0,100.0,0,'');
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(33,'d1',5.0,100.0,0,'');
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(34,'a1',100.0,1100.0,1,'');
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(35,'a2',50.0,1100.0,1,'');
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(36,'b1',100.0,1100.0,1,'');
INSERT INTO "alleles" (id,name,value,dominance,additive,epistasis) VALUES(37,'b2',50.0,1100.0,1,'');

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
INSERT INTO "character_genes" (id,character_id,gene_id) VALUES(6,3,6);
INSERT INTO "character_genes" (id,character_id,gene_id) VALUES(12,3,12);
INSERT INTO "character_genes" (id,character_id,gene_id) VALUES(14,7,14);
INSERT INTO "character_genes" (id,character_id,gene_id) VALUES(15,7,15);
INSERT INTO "character_genes" (id,character_id,gene_id) VALUES(16,7,16);
INSERT INTO "character_genes" (id,character_id,gene_id) VALUES(17,7,17);
INSERT INTO "character_genes" (id,character_id,gene_id) VALUES(18,10,18);
INSERT INTO "character_genes" (id,character_id,gene_id) VALUES(19,10,19);

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
INSERT INTO "characters" (id,name,creator_id,is_public,is_visible,sex,substrates) VALUES(2,'color',5,0,0,0,2);
INSERT INTO "characters" (id,name,creator_id,is_public,is_visible,sex,substrates) VALUES(3,'altura',5,0,1,0,3);
INSERT INTO "characters" (id,name,creator_id,is_public,is_visible,sex,substrates) VALUES(7,'prueba',5,0,1,0,3);
INSERT INTO "characters" (id,name,creator_id,is_public,is_visible,sex,substrates) VALUES(10,'altura',6,1,1,0,3);

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
INSERT INTO "connections" (id,character_id,state_a,transition,state_b) VALUES(5,2,0,1,1);
INSERT INTO "connections" (id,character_id,state_a,transition,state_b) VALUES(23,3,0,6,1);
INSERT INTO "connections" (id,character_id,state_a,transition,state_b) VALUES(24,3,1,12,2);
INSERT INTO "connections" (id,character_id,state_a,transition,state_b) VALUES(28,7,0,14,1);
INSERT INTO "connections" (id,character_id,state_a,transition,state_b) VALUES(46,7,1,15,2);
INSERT INTO "connections" (id,character_id,state_a,transition,state_b) VALUES(47,7,1,16,2);
INSERT INTO "connections" (id,character_id,state_a,transition,state_b) VALUES(48,7,1,17,2);
INSERT INTO "connections" (id,character_id,state_a,transition,state_b) VALUES(49,10,0,18,1);
INSERT INTO "connections" (id,character_id,state_a,transition,state_b) VALUES(50,10,1,19,2);

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
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(9,1,9);
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(23,6,23);
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(24,6,24);
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(29,12,28);
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(30,12,29);
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(31,14,30);
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(32,15,31);
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(33,16,32);
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(34,17,33);
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(35,18,34);
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(36,18,35);
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(37,19,36);
INSERT INTO "gene_alleles" (id,gene_id,allele_id) VALUES(38,19,37);

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
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(10,6,1,100,'random');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(31,6,2,50,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(32,6,3,50,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(33,6,4,50,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(34,6,5,50,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(35,6,6,50,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(36,6,7,50,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(37,6,8,50,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(38,6,9,50,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(39,6,10,50,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(40,6,11,50,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(46,10,1,100,'random');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(47,10,2,10,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(48,10,3,10,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(49,10,4,10,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(50,10,5,10,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(51,10,6,10,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(52,10,7,10,'cross');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(53,11,1,100,'random');
INSERT INTO "generations" (id,project_id,generation_number,population_size,type) VALUES(54,10,8,100,'random');

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
INSERT INTO "genes" (id,name,chromosome,position,code) VALUES(1,'A','1 (A, B)','1','');
INSERT INTO "genes" (id,name,chromosome,position,code) VALUES(6,'A','1','1','AB');
INSERT INTO "genes" (id,name,chromosome,position,code) VALUES(12,'B','2','1','AB');
INSERT INTO "genes" (id,name,chromosome,position,code) VALUES(14,'A','1','1','AB');
INSERT INTO "genes" (id,name,chromosome,position,code) VALUES(15,'b','2','1','AB');
INSERT INTO "genes" (id,name,chromosome,position,code) VALUES(16,'c','3','1','AB');
INSERT INTO "genes" (id,name,chromosome,position,code) VALUES(17,'d','4','1','AB');
INSERT INTO "genes" (id,name,chromosome,position,code) VALUES(18,'A','1','1','AB');
INSERT INTO "genes" (id,name,chromosome,position,code) VALUES(19,'B','2','1','AB');

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
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(101,6,2,12,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(102,6,2,14,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(103,6,2,15,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(104,6,2,19,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(105,6,3,44,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(106,6,3,45,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(107,6,3,46,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(108,6,3,48,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(109,6,4,1,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(110,6,4,2,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(111,6,4,4,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(112,6,4,5,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(113,6,5,84,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(114,6,5,85,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(115,6,5,86,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(116,6,5,87,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(117,6,6,9,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(118,6,6,10,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(119,6,6,11,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(120,6,6,13,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(121,6,7,34,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(122,6,7,35,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(123,6,7,36,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(124,6,7,38,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(125,6,8,77,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(126,6,8,79,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(127,6,8,80,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(128,6,8,81,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(129,6,9,47,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(130,6,9,51,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(131,6,9,54,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(132,6,9,56,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(133,6,10,26,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(134,6,10,28,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(135,6,10,29,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(136,6,10,30,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(137,6,11,62,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(138,6,11,64,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(139,6,11,65,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(140,6,11,68,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(141,10,2,86,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(142,10,2,91,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(143,10,2,4,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(144,10,2,6,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(145,10,3,51,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(146,10,3,54,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(147,10,3,60,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(148,10,3,63,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(149,10,4,5,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(150,10,4,35,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(151,10,4,36,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(152,10,4,40,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(153,10,5,13,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(154,10,5,15,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(155,10,5,16,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(156,10,5,27,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(157,10,6,23,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(158,10,6,38,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(159,10,6,53,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(160,10,6,58,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(161,10,7,1,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(162,10,7,23,1);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(163,10,7,6,2);
INSERT INTO "parentals" (id,project_id,generation_number,individual_id,parent_generation_number) VALUES(164,10,7,7,2);

-- Table: project_allele_frequencies
CREATE TABLE project_allele_frequencies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    allele_id INTEGER NOT NULL,
    frequency REAL NOT NULL DEFAULT 0.5,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, allele_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (allele_id) REFERENCES alleles(id) ON DELETE CASCADE
)
;
INSERT INTO "project_allele_frequencies" (id,project_id,allele_id,frequency) VALUES(1,10,23,0.6);
INSERT INTO "project_allele_frequencies" (id,project_id,allele_id,frequency) VALUES(2,10,24,0.4);
INSERT INTO "project_allele_frequencies" (id,project_id,allele_id,frequency) VALUES(3,10,28,0.7);
INSERT INTO "project_allele_frequencies" (id,project_id,allele_id,frequency) VALUES(4,10,29,0.3);

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
INSERT INTO "project_characters" (id,project_id,character_id,environment) VALUES(1,6,2,0);
INSERT INTO "project_characters" (id,project_id,character_id,environment) VALUES(5,10,3,0);
INSERT INTO "project_characters" (id,project_id,character_id,environment) VALUES(6,11,10,0);

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
INSERT INTO "projects" (id,name,description,user_id) VALUES(6,'simple','',5);
INSERT INTO "projects" (id,name,description,user_id) VALUES(10,'altura','',5);
INSERT INTO "projects" (id,name,description,user_id) VALUES(11,'test','',7);

-- Table: registration_requests
CREATE TABLE "registration_requests" (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    email TEXT,
    password TEXT NOT NULL,
    role TEXT DEFAULT 'student',
    reason TEXT,
    status TEXT DEFAULT 'pending',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    processed_by INTEGER, assigned_teacher_id INTEGER,
    FOREIGN KEY (processed_by) REFERENCES users(id)
)
;
INSERT INTO "registration_requests" (id,username,email,password,role,reason,status,requested_at,processed_at,processed_by,assigned_teacher_id) VALUES(1,'mburgos','mburgos@go.ugr.es','','student','Nueva interfaz','approved','1970-01-01 00:00:00','1970-01-01 00:00:00',1,NULL);
INSERT INTO "registration_requests" (id,username,email,password,role,reason,status,requested_at,processed_at,processed_by,assigned_teacher_id) VALUES(2,'mburgos','','$2y$12$VlyvHBsH16FE41b.DACW9.vlkdv5/PgAxa1K3HNbGeCBjGvx8NjTi','student','','approved','1970-01-01 00:00:00','1970-01-01 00:00:00',1,NULL);
INSERT INTO "registration_requests" (id,username,email,password,role,reason,status,requested_at,processed_at,processed_by,assigned_teacher_id) VALUES(3,'mburgos','','$2y$12$ZzHSofb0FuMkZtwAzCxYCuwImnnuhaKCLzy3jzyBBs6JRfL.RuTHa','student','','approved','1970-01-01 00:00:00','1970-01-01 00:00:00',1,NULL);
INSERT INTO "registration_requests" (id,username,email,password,role,reason,status,requested_at,processed_at,processed_by,assigned_teacher_id) VALUES(4,'mburgos','','$2y$12$1kXBdGhdnJaZsrCFLDiwl.TA8X17glEZRJLql1S9kMC.adE0BcbHy','student','','approved','1970-01-01 00:00:00','1970-01-01 00:00:00',1,NULL);
INSERT INTO "registration_requests" (id,username,email,password,role,reason,status,requested_at,processed_at,processed_by,assigned_teacher_id) VALUES(6,'profe','mburgos@go.ugr.es','$2y$12$9ex4j0sZi1yntQjMtwKM3egVZA.b03r3fei5Y0w62TKPV1cwN2M3.','teacher','probar caracteres públicos','approved','1970-01-01 00:00:00','1970-01-01 00:00:00',1,NULL);
INSERT INTO "registration_requests" (id,username,email,password,role,reason,status,requested_at,processed_at,processed_by,assigned_teacher_id) VALUES(7,'alumno1','','$2y$12$mKcmmTFwcMbmqtTqPrcp0.EclOlvX0BhzoM/uPR26o8JlP0T1WeJO','student','','approved','1970-01-01 00:00:00','1970-01-01 00:00:00',6,6);

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
, role TEXT DEFAULT 'student', assigned_teacher_id INTEGER REFERENCES users(id), must_change_password INTEGER DEFAULT 0)
;
INSERT INTO "users" (id,username,password,email,is_admin,is_approved,requested_at,approved_at,role,assigned_teacher_id,must_change_password) VALUES(1,'admin','$2y$12$EBhHhO8caCDeMnWjfYyBK.6iDdxbnCKdmPGn8sfZLeTq4tGFfhSpq','admin@ngw.local',1,1,'1970-01-01 00:00:00',NULL,'admin',NULL,0);
INSERT INTO "users" (id,username,password,email,is_admin,is_approved,requested_at,approved_at,role,assigned_teacher_id,must_change_password) VALUES(5,'mburgos','$2y$12$1kXBdGhdnJaZsrCFLDiwl.TA8X17glEZRJLql1S9kMC.adE0BcbHy','',0,1,'1970-01-01 00:00:00','1970-01-01 00:00:00','student',7,0);
INSERT INTO "users" (id,username,password,email,is_admin,is_approved,requested_at,approved_at,role,assigned_teacher_id,must_change_password) VALUES(6,'profe','$2y$12$9ex4j0sZi1yntQjMtwKM3egVZA.b03r3fei5Y0w62TKPV1cwN2M3.','mburgos@go.ugr.es',0,1,'1970-01-01 00:00:00','1970-01-01 00:00:00','teacher',NULL,0);
INSERT INTO "users" (id,username,password,email,is_admin,is_approved,requested_at,approved_at,role,assigned_teacher_id,must_change_password) VALUES(7,'alumno1','$2y$12$3juwvAMmoflMpIc//vN0IuUae2JPhlBZ6AUVveASObdYLJeak2/xy','',0,1,'1970-01-01 00:00:00','1970-01-01 00:00:00','student',6,0);

