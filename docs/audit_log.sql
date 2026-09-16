CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `model` VARCHAR(255) NOT NULL,
    `model_id` BIGINT NULL,
    `start_time_ms` BIGINT NOT NULL,
    `duration_ms` INT NULL,
    `action` VARCHAR(64) NOT NULL,
    `request_diff` JSON NULL,
    `reactive_diff` JSON NULL,
    `user_id` INT NULL,
    `user_info` JSON NULL,
    `initiator_audit_log_id` INT NULL,
    `descr` TEXT NULL,

    PRIMARY KEY (`id`),

    INDEX `idx_audit_log_model_model_id` (`model`, `model_id`),
    INDEX `idx_audit_log_user_id` (`user_id`),
    INDEX `fk_audit_log_initiatior_idx` (`initiator_audit_log_id` ASC),

    CONSTRAINT `fk_audit_log_initiatior`
        FOREIGN KEY (`initiator_audit_log_id`)
        REFERENCES `audit_log` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
)
ENGINE = InnoDB;
