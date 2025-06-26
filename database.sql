-- Script SQL para criação do banco de dados PostgreSQL
-- SOL TECH ENERGIA SOLAR - Sistema de Galeria de Projetos

-- Criação da tabela de categorias
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Criação da tabela de projetos (versão corrigida, sem specs diretas)
CREATE TABLE projects (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    description TEXT,
    detailed_description TEXT,
    status VARCHAR(20) DEFAULT 'active',
    featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Criação da tabela de imagens dos projetos
CREATE TABLE project_images (
    id SERIAL PRIMARY KEY,
    project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    image_path VARCHAR(500) NOT NULL,
    alt_text VARCHAR(255),
    is_primary BOOLEAN DEFAULT FALSE,
    order_position INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Criação da tabela de especificações técnicas
CREATE TABLE project_specs (
    id SERIAL PRIMARY KEY,
    project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    spec_name VARCHAR(100) NOT NULL,
    spec_value VARCHAR(255) NOT NULL,
    spec_icon VARCHAR(50),
    order_position INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, spec_name) -- Garante que cada projeto tenha apenas um tipo de spec
);

-- Criação da tabela de contatos/leads
-- Criação da tabela de contatos/leads (VERSÃO MODIFICADA)

CREATE TABLE contacts (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    city VARCHAR(100),
    consumption DECIMAL(10,2),
    message TEXT,
    source VARCHAR(50) DEFAULT 'website',
    -- O status agora é um tipo ENUM para garantir a consistência dos dados
    status VARCHAR(50) NOT NULL DEFAULT 'novo' CHECK (status IN ('novo', 'entramos em contato', 'vendido', 'perdido', 'lixeira')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    -- Nova coluna para controlar o tempo de exclusão da lixeira
    deleted_at TIMESTAMP WITH TIME ZONE
);


-- Criação da tabela de usuários administrativos
CREATE TABLE admin_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role VARCHAR(20) DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inserção dos dados iniciais

-- Categorias
INSERT INTO categories (name, slug, description) VALUES
('Telhado Metálico', 'telhado-metalico', 'Instalações em telhados metálicos industriais e comerciais'),
('Telhado Cerâmico', 'telhado-ceramico', 'Instalações residenciais em telhados cerâmicos tradicionais'),
('Telhado Fibrocimento', 'telhado-fibrocimento', 'Instalações rurais e industriais em telhados de fibrocimento'),
('Laje', 'laje', 'Instalações em lajes de edifícios e condomínios'),
('Solo', 'solo', 'Usinas solares instaladas no solo com estruturas de suporte'),
('Garagem', 'garagem', 'Coberturas fotovoltaicas para estacionamentos e garagens');

-- Projetos (sem as colunas de specs)
INSERT INTO projects (id, title, slug, category_id, description, detailed_description, featured) VALUES
(1, 'Usina Solar Industrial 500kWp', 'usina-solar-industrial-500kwp', 1, 'Projeto industrial de grande porte com sistema de 500kWp instalado em telhado metálico.', 'Este projeto industrial representa um marco em nossa trajetória...', TRUE),
(2, 'Residência Premium 15kWp', 'residencia-premium-15kwp', 2, 'Sistema residencial premium com 15kWp perfeitamente integrado ao telhado cerâmico.', 'Este projeto residencial demonstra como a energia solar pode ser integrada harmoniosamente...', TRUE),
(3, 'Agronegócio - Pivô de Irrigação 1MWp', 'agronegocio-pivo-irrigacao-1mwp', 5, 'Usina solar no solo de 1MWp dedicada ao sistema de irrigação de uma propriedade rural.', 'Projeto inovador que combina agricultura sustentável com energia renovável...', TRUE),
(4, 'Condomínio Comercial 200kWp', 'condominio-comercial-200kwp', 4, 'Sistema comercial instalado em laje de condomínio empresarial.', 'Este projeto atende um condomínio empresarial com 12 salas comerciais...', FALSE),
(5, 'Galpão Rural 80kWp', 'galpao-rural-80kwp', 3, 'Sistema rural instalado em galpão com telhado de fibrocimento.', 'Projeto desenvolvido para uma propriedade rural focada em atividades pecuárias...', FALSE),
(6, 'Estacionamento Solar 120kWp', 'estacionamento-solar-120kwp', 6, 'Cobertura fotovoltaica para estacionamento comercial.', 'Projeto inovador que combina proteção veicular com geração de energia renovável...', FALSE);

-- Especificações dos Projetos
INSERT INTO project_specs (project_id, spec_name, spec_value) VALUES
(1, 'power', '500 kWp'), (1, 'panels', '1.250 unidades'), (1, 'area', '3.200 m²'), (1, 'savings', 'R$ 45.000/mês'), (1, 'location', 'São Luís de Montes Belos - GO'), (1, 'year', '2024'),
(2, 'power', '15 kWp'), (2, 'panels', '38 unidades'), (2, 'area', '95 m²'), (2, 'savings', 'R$ 850/mês'), (2, 'location', 'Palmeiras de Goiás - GO'), (2, 'year', '2024'),
(3, 'power', '1 MWp'), (3, 'panels', '2.500 unidades'), (3, 'area', '8.000 m²'), (3, 'savings', 'R$ 75.000/mês'), (3, 'location', 'Fazenda Santa Clara - GO'), (3, 'year', '2024'),
(4, 'power', '200 kWp'), (4, 'panels', '500 unidades'), (4, 'area', '1.280 m²'), (4, 'savings', 'R$ 18.000/mês'), (4, 'location', 'Centro Empresarial - Goiânia - GO'), (4, 'year', '2024'),
(5, 'power', '80 kWp'), (5, 'panels', '200 unidades'), (5, 'area', '512 m²'), (5, 'savings', 'R$ 6.500/mês'), (5, 'location', 'Fazenda Bela Vista - GO'), (5, 'year', '2023'),
(6, 'power', '120 kWp'), (6, 'panels', '300 unidades'), (6, 'area', '768 m²'), (6, 'savings', 'R$ 9.200/mês'), (6, 'location', 'Shopping Center - Anápolis - GO'), (6, 'year', '2024');

-- Usuário Admin (senha: admin123)
INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@soltech.com.br', '$2y$10$PEpHXlmeANmOeZQGiKmAqO2pNRhksE/pkMF7emmBNKyoR9AizC/Zu', 'Administrador SOL TECH', 'admin');

-- Criação de trigger para atualizar updated_at automaticamente
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_projects_updated_at 
    BEFORE UPDATE ON projects 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_admin_users_updated_at 
    BEFORE UPDATE ON admin_users 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

-- Índices para otimização
CREATE INDEX idx_projects_category ON projects(category_id);
CREATE INDEX idx_project_images_project ON project_images(project_id);
CREATE INDEX idx_project_specs_project ON project_specs(project_id);
CREATE INDEX idx_contacts_created ON contacts(created_at);
CREATE INDEX idx_admin_users_username ON admin_users(username);

-- ====================================================================
-- SINCRONIZAÇÃO DAS SEQUENCES APÓS INSERÇÃO MANUAL DE DADOS
-- Adicionar este bloco ao FINAL do script database.sql
-- ====================================================================

-- Sincroniza o contador da tabela 'projects' com o maior ID existente nela
SELECT setval('projects_id_seq', (SELECT MAX(id) FROM projects));

-- Sincroniza o contador da tabela 'categories'
SELECT setval('categories_id_seq', (SELECT MAX(id) FROM categories));

-- Sincroniza o contador da tabela 'project_specs'
SELECT setval('project_specs_id_seq', (SELECT MAX(id) FROM project_specs));

-- Sincroniza o contador da tabela 'admin_users'
SELECT setval('admin_users_id_seq', (SELECT MAX(id) FROM admin_users));