CREATE TABLE tx_maispace_consent_category (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    tstamp int(11) DEFAULT '0' NOT NULL,
    crdate int(11) DEFAULT '0' NOT NULL,
    deleted tinyint(4) DEFAULT '0' NOT NULL,
    hidden tinyint(4) DEFAULT '0' NOT NULL,
    sorting int(11) DEFAULT '0' NOT NULL,
    name varchar(255) DEFAULT '' NOT NULL,
    description text,
    is_essential tinyint(1) DEFAULT '0' NOT NULL,
    PRIMARY KEY (uid),
    KEY parent (pid)
);

CREATE TABLE tx_maispace_consent_statistic (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    tstamp int(11) DEFAULT '0' NOT NULL,
    category_uid int(11) DEFAULT '0' NOT NULL,
    accepted tinyint(1) DEFAULT '0' NOT NULL,
    PRIMARY KEY (uid),
    KEY category (category_uid),
    KEY tstamp (tstamp)
);

CREATE TABLE tt_content (
    tx_maispace_consent_categories varchar(255) DEFAULT '' NOT NULL
);
