-- Create Database
CREATE DATABASE IF NOT EXISTS kazilink_db;
USE kazilink_db;

-- 1. Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employer', 'seeker') NOT NULL,
    phone VARCHAR(20),
    avatar VARCHAR(50) DEFAULT '👤',
    company VARCHAR(100),
    bio TEXT,
    skills TEXT, -- Stored as comma-separated string for simplicity
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Jobs Table
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employer_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(100) NOT NULL,
    salary DECIMAL(10, 2) NOT NULL,
    deadline DATE NOT NULL,
    job_type VARCHAR(50) NOT NULL,
    status ENUM('active', 'closed') DEFAULT 'active',
    required_qualifications TEXT, -- Stored as JSON or comma-separated
    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Applications Table
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    applicant_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 4. User Qualifications Table
CREATE TABLE user_qualifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    qualification_name VARCHAR(150) NOT NULL,
    issuer VARCHAR(150) NOT NULL,
    year_obtained VARCHAR(10),
    document_path VARCHAR(255), -- Path to uploaded file
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. User Experience Table
CREATE TABLE user_experience (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    job_title VARCHAR(150) NOT NULL,
    duration VARCHAR(50) NOT NULL,
    proof_details TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert Sample Data (Passwords are hashed: 'password123')
INSERT INTO users (full_name, email, password, role, phone, avatar, company, bio, skills) VALUES 
('System Admin', 'admin@kazilink.co.ke', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '0700-0001', '‍💼', NULL, 'Administrator', ''),
('Nairobi Tech Corp', 'hr@techcorp.co.ke', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employer', '0700-0002', '‍💼', 'Nairobi Tech Corp', '', ''),
('John Kamau', 'john@seeker.co.ke', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seeker', '0712-3456', '👨‍💻', NULL, 'Experienced developer', 'JavaScript,React,Node.js'),
('Jane Wanjiku', 'jane@seeker.co.ke', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seeker', '0722-9876', '👩‍🌾', NULL, 'Passionate gardener', 'Landscaping,Farming');

INSERT INTO jobs (employer_id, title, category, description, location, salary, deadline, job_type, required_qualifications) VALUES 
(2, 'Senior Frontend Developer', 'Technology', 'Build amazing UIs.', 'Remote / Nairobi', 180000.00, '2024-12-31', 'Full-time', 'BSc Computer Science, 3+ years experience'),
(2, 'Backend Engineer', 'Technology', 'Scale backend systems.', 'Remote', 200000.00, '2024-11-15', 'Full-time', 'Node.js, PostgreSQL'),
(5, 'Professional Gardener', 'Agriculture', 'Maintain luxury estates.', 'Mombasa', 45000.00, '2024-10-30', 'Contract', 'Diploma in Horticulture');
