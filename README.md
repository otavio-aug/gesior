In order to use the landing page with an gesior AAC, follow commit `aaecfb9bea64b2e0da75ce19f02288e92109de4f` (headers outdated, check last commit).

Now have the pix api location!

For use, run thhese querys in your database:
```sql
CREATE TABLE transactions_mercadopagopix (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    account_name VARCHAR(255) NOT NULL,
    player_name VARCHAR(255) NOT NULL,
    player_id INT NOT NULL,
    external_reference VARCHAR(255) NOT NULL UNIQUE,     
    mercado_pago_id VARCHAR(255) NOT NULL UNIQUE,  
    transaction_amount DECIMAL(10,2) NOT NULL,  
    points INT NOT NULL,         
    payer_email VARCHAR(255) NOT NULL,                   
    payer_first_name VARCHAR(255),                       
    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',                
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;

ALTER TABLE accounts ADD COLUMN points INT DEFAULT 0;
```

Set your mercado pago production developer token in config/config.php at $config['MercadoPago']['token'].