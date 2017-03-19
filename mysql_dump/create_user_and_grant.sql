CREATE USER 'bpmspace_repl'@'localhost' IDENTIFIED BY 'PASSWORTinLASTPASS';
GRANT SELECT, INSERT, UPDATE ON `bpmspace_replacer_v1`.* TO 'bpmspace_repl'@'localhost';
CREATE USER 'bpmspace_repl_RO'@'localhost' IDENTIFIED BY 'PASSWORTinLASTPASS';
GRANT SELECT ON `bpmspace_replacer_v1`.* TO 'bpmspace_repl_RO'@'localhost';