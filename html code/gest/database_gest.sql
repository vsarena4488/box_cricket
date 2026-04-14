CREATE DATABASE box_cricket;
USE box_cricket;

-- TOURNAMENT TABLE
CREATE TABLE tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team1 VARCHAR(100),
    team2 VARCHAR(100),
    match_date DATE,
    match_time TIME,
    location VARCHAR(150),
    slots_left INT,
    status ENUM('available','full','coming'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- CONTACT TABLE
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100),
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- =====================================================
-- INSERT TOURNAMENT MATCHES DATA
-- =====================================================

-- Insert sample tournament matches into the database
INSERT INTO tournaments (team1, team2, match_date, match_time, location, slots_left, status) VALUES

-- Match 1: Super Strikers vs Royal Challengers
('Super Strikers', 'Royal Challengers', '2026-04-10', '19:00:00', 'Box Cricket Arena, Sector 62', 3, 'available'),
-- Status: available (3 spots remaining)
-- Time: 7:00 PM

-- Match 2: Mighty Warriors vs Thunderbolts
('Mighty Warriors', 'Thunderbolts', '2026-04-12', '20:00:00', 'Sports Complex, Noida', 0, 'full'),
-- Status: full (no spots remaining)
-- Time: 8:00 PM

-- Match 3: Power Hitters vs Fast Fighters
('Power Hitters', 'Fast Fighters', '2026-04-15', '18:30:00', 'Indoor Cricket Hub, Delhi', 5, 'available'),
-- Status: available (5 spots remaining)
-- Time: 6:30 PM

-- Match 4: Blazing Batsmen vs Night Riders
('Blazing Batsmen', 'Night Riders', '2026-04-18', '21:00:00', 'City Sports Arena, Mumbai', 2, 'available'),
-- Status: available (2 spots remaining)
-- Time: 9:00 PM

-- Match 5: Rising Stars vs Champion Kings
('Rising Stars', 'Champion Kings', '2026-04-20', '17:00:00', 'Elite Cricket Ground, Ahmedabad', 10, 'coming');
-- Status: coming soon (match not yet open for booking)
-- Time: 5:00 PM
-- Note: 10 spots available when booking opens



-- =====================================================
-- INSERT CONTACT MESSAGES
-- =====================================================

-- Insert sample contact messages into the database
INSERT INTO contact_messages (name, email, message) VALUES

-- Message 1: Vishal Patel - Tournament booking inquiry
(
    'Vishal Patel',                    -- Sender name
    'vishal@gmail.com',                -- Sender email
    'I want to know about tournament booking process.'  -- Message content
),

-- Message 2: Rahul Sharma - Weekend slot availability
(
    'Rahul Sharma',                    -- Sender name
    'rahul@gmail.com',                 -- Sender email
    'Is slot booking available for weekends?'  -- Message content
),

-- Message 3: Amit Verma - Cancellation policy inquiry
(
    'Amit Verma',                      -- Sender name
    'amit@gmail.com',                  -- Sender email
    'Can I cancel my booking after payment?'  -- Message content
),

-- Message 4: Sneha Shah - Equipment rental inquiry
(
    'Sneha Shah',                      -- Sender name
    'sneha@gmail.com',                 -- Sender email
    'Do you provide cricket kits on rent?'  -- Message content
),

-- Message 5: Karan Mehta - Tournament participation inquiry
(
    'Karan Mehta',                     -- Sender name
    'karan@gmail.com',                 -- Sender email
    'I want to join upcoming tournaments, please guide.'  -- Message content
);



-- =====================================================================================================================================================================================

