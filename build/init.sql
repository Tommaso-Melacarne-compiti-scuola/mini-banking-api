-- Tabella accounts
-- Campi minimi consigliati:

-- id
-- owner_name
-- currency
-- created_at
-- Tabella transactions
-- Campi minimi consigliati:

-- id
-- account_id
-- type (deposit oppure withdrawal)
-- amount
-- description
-- created_at
-- Campo opzionale utile
-- Potete aggiungere anche:

-- balance_after
-- Questo campo salva il saldo risultante dopo ogni operazione e può aiutarvi nei controlli.

CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_name VARCHAR(255) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    type ENUM('deposit', 'withdrawal') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    balance_after DECIMAL(10, 2),
    FOREIGN KEY (account_id) REFERENCES accounts(id)
);

INSERT INTO accounts (owner_name, currency) VALUES ('Alice', 'EUR');