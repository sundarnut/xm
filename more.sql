drop table if exists xmSourceTargets01;

create table xmSourceTargets01 (
    sourceTargetId                         int ( 10 ) unsigned NOT NULL AUTO_INCREMENT,
    name                                   varchar( 64 ) NOT NULL,
    sequenceId                             int ( 10 ) unsigned NOT NULL,
    groupId                                int ( 10 ) unsigned DEFAULT NULL,
    isPrimary                              tinyint ( 1 ) unsigned NOT NULL DEFAULT 0,
    enabled                                tinyint ( 1 ) unsigned NOT NULL DEFAULT 0,
    created                                datetime NOT NULL,
    lastUpdated                            datetime NOT NULL,
    PRIMARY KEY ix_sourceTargetId ( sourceTargetId ),
    INDEX ix_name ( name )
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

drop table if exists xmSourceTargetGroups01;

create table xmSourceTargetGroups01 (
    sourceTargetGroupId                    int ( 10 ) unsigned NOT NULL AUTO_INCREMENT,
    name                                   varchar( 64 ) NOT NULL,
    groupId                                int ( 10 ) unsigned DEFAULT NULL,
    enabled                                tinyint ( 1 ) unsigned NOT NULL DEFAULT 0,
    created                                datetime NOT NULL,
    lastUpdated                            datetime NOT NULL,
    PRIMARY KEY ( sourceTargetGroupId ),
    INDEX ix_name ( name )
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

drop table if exists xmSourceTargetMapping01;

create table xmSourceTargetMapping01 (
    mappingId                              int ( 10 ) unsigned NOT NULL AUTO_INCREMENT,
    userId                                 int ( 10 ) unsigned NOT NULL,
    sourceTargetId                         int ( 10 ) unsigned NOT NULL,
    enabled                                tinyint ( 1 ) unsigned NOT NULL DEFAULT 0,
    created                                datetime NOT NULL,
    lastUpdated                            datetime NOT NULL,
    PRIMARY KEY ( mappingId ),
    UNIQUE INDEX ix_sourceTargetId ( sourceTargetId )
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

