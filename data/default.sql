CREATE TABLE `acl_roles` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`role_id` VARCHAR(36) NOT NULL,
	`parent` VARCHAR(36) NOT NULL,
	PRIMARY KEY (`id`),
	INDEX `k_acl_roles_role_id` (`role_id`),
	FOREIGN KEY `fk_acl_roles_parent` (`parent`)
		REFERENCES `acl_roles` (`role_id`)
		ON UPDATE CASCADE ON DELETE CASCADE
);