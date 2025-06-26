-- Script SQL para criação do banco de dados MySQL
-- SOL TECH ENERGIA SOLAR - Versão para MySQL

-- Tabela de categorias
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de projetos
CREATE TABLE `projects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `category_id` INT,
    `description` TEXT,
    `detailed_description` TEXT,
    `status` VARCHAR(20) DEFAULT 'active',
    `featured` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de mídias dos projetos
CREATE TABLE `project_media` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `media_type` VARCHAR(10) NOT NULL DEFAULT 'image',
    `alt_text` VARCHAR(255),
    `is_primary` BOOLEAN DEFAULT FALSE,
    `order_position` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de especificações técnicas
CREATE TABLE `project_specs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `spec_name` VARCHAR(100) NOT NULL,
    `spec_value` VARCHAR(255) NOT NULL,
    `spec_icon` VARCHAR(50),
    `order_position` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `project_id_spec_name` (`project_id`, `spec_name`),
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de contatos/leads
CREATE TABLE `contacts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50),
    `city` VARCHAR(100),
    `consumption` DECIMAL(10,2),
    `message` TEXT,
    `source` VARCHAR(50) DEFAULT 'website',
    `status` VARCHAR(50) NOT NULL DEFAULT 'novo',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de usuários administrativos
CREATE TABLE `admin_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(255),
    `role` VARCHAR(20) DEFAULT 'admin',
    `is_active` BOOLEAN DEFAULT TRUE,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserção dos dados iniciais (sintaxe compatível com MySQL)
INSERT INTO `categories` (`name`, `slug`, `description`) VALUES
('Telhado Metálico', 'telhado-metalico', 'Instalações em telhados metálicos industriais e comerciais'),
('Telhado Cerâmico', 'telhado-ceramico', 'Instalações residenciais em telhados cerâmicos tradicionais'),
('Telhado Fibrocimento', 'telhado-fibrocimento', 'Instalações rurais e industriais em telhados de fibrocimento'),
('Laje', 'laje', 'Instalações em lajes de edifícios e condomínios'),
('Solo', 'solo', 'Usinas solares instaladas no solo com estruturas de suporte'),
('Garagem', 'garagem', 'Coberturas fotovoltaicas para estacionamentos e garagens');

INSERT INTO `projects` (`id`, `title`, `slug`, `category_id`, `description`, `detailed_description`, `featured`) VALUES
(1, 'Usina Solar Industrial 500kWp', 'usina-solar-industrial-500kwp', 1, 'Projeto industrial de grande porte com sistema de 500kWp instalado em telhado metálico.', 'Este projeto industrial representa um marco em nossa trajetória...', TRUE),
(2, 'Residência Premium 15kWp', 'residencia-premium-15kwp', 2, 'Sistema residencial premium com 15kWp perfeitamente integrado ao telhado cerâmico.', 'Este projeto residencial demonstra como a energia solar pode ser integrada harmoniosamente...', TRUE),
(3, 'Agronegócio - Pivô de Irrigação 1MWp', 'agronegocio-pivo-irrigacao-1mwp', 5, 'Usina solar no solo de 1MWp dedicada ao sistema de irrigação de uma propriedade rural.', 'Projeto inovador que combina agricultura sustentável com energia renovável...', TRUE),
(4, 'Condomínio Comercial 200kWp', 'condominio-comercial-200kwp', 4, 'Sistema comercial instalado em laje de condomínio empresarial.', 'Este projeto atende um condomínio empresarial com 12 salas comerciais...', FALSE),
(5, 'Galpão Rural 80kWp', 'galpao-rural-80kwp', 3, 'Sistema rural instalado em galpão com telhado de fibrocimento.', 'Projeto desenvolvido para uma propriedade rural focada em atividades pecuárias...', FALSE),
(6, 'Estacionamento Solar 120kWp', 'estacionamento-solar-120kwp', 6, 'Cobertura fotovoltaica para estacionamento comercial.', 'Projeto inovador que combina proteção veicular com geração de energia renovável...', FALSE);

INSERT INTO `project_specs` (`project_id`, `spec_name`, `spec_value`) VALUES
(1, 'power', '500 kWp'), (1, 'panels', '1.250 unidades'), (1, 'area', '3.200 m²'), (1, 'savings', 'R$ 45.000/mês'), (1, 'location', 'São Luís de Montes Belos - GO'), (1, 'year', '2024'),
(2, 'power', '15 kWp'), (2, 'panels', '38 unidades'), (2, 'area', '95 m²'), (2, 'savings', 'R$ 850/mês'), (2, 'location', 'Palmeiras de Goiás - GO'), (2, 'year', '2024'),
(3, 'power', '1 MWp'), (3, 'panels', '2.500 unidades'), (3, 'area', '8.000 m²'), (3, 'savings', 'R$ 75.000/mês'), (3, 'location', 'Fazenda Santa Clara - GO'), (3, 'year', '2024'),
(4, 'power', '200 kWp'), (4, 'panels', '500 unidades'), (4, 'area', '1.280 m²'), (4, 'savings', 'R$ 18.000/mês'), (4, 'location', 'Centro Empresarial - Goiânia - GO'), (4, 'year', '2024'),
(5, 'power', '80 kWp'), (5, 'panels', '200 unidades'), (5, 'area', '512 m²'), (5, 'savings', 'R$ 6.500/mês'), (5, 'location', 'Fazenda Bela Vista - GO'), (5, 'year', '2023'),
(6, 'power', '120 kWp'), (6, 'panels', '300 unidades'), (6, 'area', '768 m²'), (6, 'savings', 'R$ 9.200/mês'), (6, 'location', 'Shopping Center - Anápolis - GO'), (6, 'year', '2024');

INSERT INTO `admin_users` (`username`, `email`, `password_hash`, `full_name`, `role`) VALUES
('admin', 'admin@soltech.com.br', '$2y$10$PEpHXlmeANmOeZQGiKmAqO2pNRhksE/pkMF7emmBNKyoR9AizC/Zu', 'Administrador SOL TECH', 'admin');

-- Índices para otimização
CREATE INDEX `idx_projects_category` ON `projects`(`category_id`);
CREATE INDEX `idx_project_media_project` ON `project_media`(`project_id`);
CREATE INDEX `idx_project_specs_project` ON `project_specs`(`project_id`);
CREATE INDEX `idx_contacts_created` ON `contacts`(`created_at`);
CREATE INDEX `idx_admin_users_username` ON `admin_users`(`username`);