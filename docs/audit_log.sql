CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `initiator_audit_log_id` INT NULL,
    `action` VARCHAR(32) NOT NULL,
    `model` VARCHAR(255) NOT NULL,
    `model_id` BIGINT NULL,
    `start_time_ms` BIGINT NOT NULL,
    `start_time` DATETIME NOT NULL,
    `duration` INT NULL,
    `request_diff` JSON NULL,
    `reactive_diff` JSON NULL,
    `user_id` INT NULL,
    `session_info` JSON NULL,
    `descr` TEXT NULL,

    PRIMARY KEY (`id`),

    INDEX `fk_audit_log_initiator_idx` (`initiator_audit_log_id` ASC),
    INDEX `idx_audit_log_model_model_id` (`model`, `model_id`),
    INDEX `idx_audit_log_user_id` (`user_id`),

    CONSTRAINT `fk_audit_log_initiator`
        FOREIGN KEY (`initiator_audit_log_id`)
        REFERENCES `audit_log` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
)
ENGINE = InnoDB;
