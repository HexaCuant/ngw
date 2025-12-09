-- GenWeb NG - SQLite Schema
-- Independent database schema for ngw

-- Users table with registration approval
CREATE TABLE IF NOT EXISTS users (
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
);

-- Registration requests (for pending approvals)
CREATE TABLE IF NOT EXISTS registration_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    email TEXT,
    reason TEXT,
    status TEXT DEFAULT 'pending', -- pending, approved, rejected
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    processed_by INTEGER,
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- Projects table
CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Characters table
CREATE TABLE IF NOT EXISTS characters (
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
);

-- Project-Character relationship
CREATE TABLE IF NOT EXISTS project_characters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    character_id INTEGER NOT NULL,
    environment INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, character_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
);

-- Genes table
CREATE TABLE IF NOT EXISTS genes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    chromosome TEXT,
    position TEXT,
    code TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Character-Gene relationship
CREATE TABLE IF NOT EXISTS character_genes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    character_id INTEGER NOT NULL,
    gene_id INTEGER NOT NULL,
    UNIQUE(character_id, gene_id),
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (gene_id) REFERENCES genes(id) ON DELETE CASCADE
);

-- Alleles table
CREATE TABLE IF NOT EXISTS alleles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    value REAL,
    dominance REAL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Gene-Allele relationship
CREATE TABLE IF NOT EXISTS gene_alleles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    gene_id INTEGER NOT NULL,
    allele_id INTEGER NOT NULL,
    UNIQUE(gene_id, allele_id),
    FOREIGN KEY (gene_id) REFERENCES genes(id) ON DELETE CASCADE,
    FOREIGN KEY (allele_id) REFERENCES alleles(id) ON DELETE CASCADE
);

-- Connections (state transitions)
CREATE TABLE IF NOT EXISTS connections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    character_id INTEGER NOT NULL,
    state_a INTEGER NOT NULL,
    transition INTEGER NOT NULL,
    state_b INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
);

-- Generations table
CREATE TABLE IF NOT EXISTS generations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    generation_number INTEGER NOT NULL,
    population_size INTEGER,
    type TEXT, -- 'random', 'cross'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, generation_number),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_approved ON users(is_approved);
CREATE INDEX IF NOT EXISTS idx_registration_requests_status ON registration_requests(status);
CREATE INDEX IF NOT EXISTS idx_projects_user ON projects(user_id);
CREATE INDEX IF NOT EXISTS idx_characters_creator ON characters(creator_id);
CREATE INDEX IF NOT EXISTS idx_characters_public ON characters(is_public);
CREATE INDEX IF NOT EXISTS idx_project_characters_project ON project_characters(project_id);
CREATE INDEX IF NOT EXISTS idx_project_characters_character ON project_characters(character_id);
CREATE INDEX IF NOT EXISTS idx_character_genes_character ON character_genes(character_id);
CREATE INDEX IF NOT EXISTS idx_generations_project ON generations(project_id);

-- Create default admin user (password: admin123 - CHANGE THIS!)
-- Password hash for 'admin123'
INSERT OR IGNORE INTO users (id, username, password, email, is_admin, is_approved)
VALUES (1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@ngw.local', 1, 1);
