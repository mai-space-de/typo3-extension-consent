CREATE TABLE tx_maiconsent_category (
    uid         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    pid         INT(11) UNSIGNED NOT NULL DEFAULT 0,
    tstamp      INT(11) UNSIGNED NOT NULL DEFAULT 0,
    crdate      INT(11) UNSIGNED NOT NULL DEFAULT 0,
    cruser_id   INT(11) UNSIGNED NOT NULL DEFAULT 0,
    deleted     TINYINT(4) UNSIGNED NOT NULL DEFAULT 0,
    hidden      TINYINT(4) UNSIGNED NOT NULL DEFAULT 0,
    sorting     INT(11) UNSIGNED NOT NULL DEFAULT 0,
    sys_language_uid INT(11) DEFAULT 0 NOT NULL,
    l10n_parent      INT(11) UNSIGNED DEFAULT 0 NOT NULL,
    l10n_diffsource  MEDIUMBLOB,

    title       VARCHAR(255) NOT NULL DEFAULT '',
    identifier  VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT,
    is_required TINYINT(4) UNSIGNED NOT NULL DEFAULT 0,

    PRIMARY KEY (uid),
    KEY parent (pid)
);

CREATE TABLE tx_maiconsent_log (
    uid         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    pid         INT(11) UNSIGNED NOT NULL DEFAULT 0,
    tstamp      INT(11) UNSIGNED NOT NULL DEFAULT 0,
    crdate      INT(11) UNSIGNED NOT NULL DEFAULT 0,
    cruser_id   INT(11) UNSIGNED NOT NULL DEFAULT 0,
    deleted     TINYINT(4) UNSIGNED NOT NULL DEFAULT 0,

    category    INT(11) UNSIGNED NOT NULL DEFAULT 0,
    accepted    TINYINT(4) UNSIGNED NOT NULL DEFAULT 0,
    session     VARCHAR(255) NOT NULL DEFAULT '',
    ip_address  VARCHAR(45) NOT NULL DEFAULT '',

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY category (category)
);
