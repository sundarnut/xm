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
    userId                                 int ( 10 ) unsigned NOT NULL,
    groupId                                int ( 10 ) unsigned DEFAULT NULL,
    name                                   varchar( 64 ) NOT NULL,
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

drop table if exists xmSourceTargetChangeLogs01;

create table xmSourceTargetChangeLogs01 (
    logId                                  int ( 10 ) unsigned NOT NULL AUTO_INCREMENT,
    sourceTargetId                         int ( 10 ) unsigned NOT NULL,
    log                                    varchar( 8192 ) DEFAULT NULL,
    created                                datetime NOT NULL,
    PRIMARY KEY ( logId ),
    INDEX ix_sourceTargetId ( sourceTargetId )
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

drop procedure if exists createOrUpdateSourceTargets01;

delimiter //

create procedure createOrUpdateSourceTargets01 (
    in p_sourceTargetId                       int ( 10 ) unsigned,
    in p_userId                               int ( 10 ) unsigned,
    in p_name                                 varchar( 64 ),
    in p_groupId                              int ( 10 ) unsigned,
    in p_sequenceId                           int ( 10 ) unsigned,
    in p_isPrimary                            tinyint ( 1 ) unsigned,
    in p_enabled                              tinyint ( 1 ) unsigned
)
begin

    declare l_query                          varchar( 1024 );
    declare l_message                        varchar( 1024 );
    declare l_sourceTargetId                 int ( 10 ) unsigned;
    declare l_errorFlag                      tinyint ( 1 ) unsigned;
    declare l_errorMessage                   varchar( 512 );
    declare l_element                        varchar( 1024 );
    declare l_groupId                        int ( 10 ) unsigned;
    declare l_sequenceId                     int ( 10 ) unsigned;
    declare l_sourceTargetGroupId            int ( 10 ) unsigned;

    set l_message = '';
    set l_errorMessage = null;
    set l_sourceTargetId = null;
    set l_groupId = p_groupId;
    set l_sequenceId = p_sequenceId;

    if p_userId is null or p_userId = 0 then

        set l_errorFlag = 1;
        set l_errorMessage = concat('userId parameter has invalid value: ', ifnull(p_userId, ' null'));

    elseif p_sourceTargetId is null and (p_name is null or p_name = '') then

        set l_errorFlag = 1;
        set l_errorMessage = concat('Incorrect value provided for name parameter, found: ', ifnull(p_name, 'null'));

    elseif p_name is not null and p_name != '' then

        select xST.sourceTargetId into l_sourceTargetId from xmSourceTargets01 xST
        inner join xmSourceTargetMapping01 xSTM on xST.sourceTargetId = xSTM.sourceTargetId
        where xST.name = p_name and xSTM.userId = p_userId;

        if l_sourceTargetId is null then
            set l_errorFlag = 0;
        elseif (p_sourceTargetId is null and l_sourceTargetId is not null) or
               (p_sourceTargetId is not null and p_sourceTargetId != l_sourceTargetId) then
            set l_errorFlag = 1;
            set l_errorMessage = concat('Found another sourceTarget row with same name, with ID: ', l_sourceTargetId);
        end if;

    end if;

    if l_errorFlag = 0 then

        if p_sourceTargetId = 0 or p_sourceTargetId is null then

            if p_isPrimary = 1 and (p_groupId is null or p_groupId = 0) then

                select groupId into l_groupId from xmSourceTargetGroups01 where userId = p_userId
                order by groupId desc limit 1;

                if l_groupId is null then
                    set l_groupId = 1;
                else
                    set l_groupId = l_groupId + 1;
                end if;

                insert xmSourceTargetGroups01 (
                    userId,
                    groupId,
                    name,
                    enabled,
                    created,
                    lastUpdated
                ) values (
                    p_userId,
                    l_groupId,
                    p_name,
                    1,
                    utc_timestamp(),
                    utc_timestamp()
                );

                select last_insert_id() into l_sourceTargetGroupId;

                set l_message = concat('{"NewSourceTargetGroup":{\n',
                                    '"name":"', replace(p_name, '"', '\"'), '",\n',
                                    '"userId":', p_userId, ',\n',
                                    '"groupId":', l_groupId, ',\n',
                                    '"sourceTargetGroupId":', l_sourceTargetGroupId, '}}');

                insert xmSourceTargetChangeLogs01 (
                        sourceTargetId, log, created
                    ) values (
                        0, l_message, utc_timestamp()
                    );
            end if;
                                              
            if l_sequenceId is null then
                select xST.sequenceId into l_sequenceId from xmSourceTargets01 xST
                inner join xmSourceTargetMapping01 xSTM on xST.sourceTargetId = xSTM.sourceTargetId
                where xSTM.userId = p_userId order by xST.sequenceId desc limit 1;
            
                if l_sequenceId is null then
                    set l_sequenceId = 1;
                else
                    set l_sequenceId = l_sequenceId + 1;
                end if;
            end if;

            insert xmSourceTargets01 (
                name,
                groupId,
                sequenceId,
                isPrimary,
                enabled,
                created,
                lastUpdated
            ) values (
                p_name,
                l_groupId,
                l_sequenceId,
                p_isPrimary,
                p_enabled,
                utc_timestamp(),
                utc_timestamp()
            );

            select last_insert_id() into l_sourceTargetId;

            insert xmSourceTargetMapping01 (
                sourceTargetId,
                userId,
                enabled,
                created,
                lastUpdated
            ) values (
                l_sourceTargetId,
                p_userId,
                1,
                utc_timestamp(),
                utc_timestamp()
            );

            set l_message = concat('{"NewSourceTarget":{\n',
                                '"name":"', replace(p_name, '"', '\"'), '",\n',
                                '"groupId":', ifnull(l_groupId, 'null'), ',\n',
                                '"isPrimary":', p_isPrimary, ',\n',
                                '"sequenceId":', l_sequenceId, ',\n',
                                '"enabled":', p_enabled, '}}');

            insert xmSourceTargetChangeLogs01 (
                sourceTargetId, log, created
            ) values (
                l_sourceTargetId, l_message, utc_timestamp()
            );

        else

            set l_query = 'update xmSourceTargets01 set ';
            set l_message = '{"OldSourceTarget":{';

            if p_name is not null then

                set l_query = concat(l_query, 'name=');

                if p_name = '' then
                    set l_query = concat(l_query, 'null');
                    set l_message = concat(l_message, '"name":null');
                else
                    set l_element = replace(p_name, '\'', '\'\'');
                    set l_query = concat(l_query, '\'', l_element, '\'');
                    set l_message = concat(l_message, '"name":"', replace(l_element, '"', '\\"'), '"');

                end if;
            end if;

            if p_groupId is not null then

                if p_groupId = 0 then
                    set p_groupId = null;
                end if;

                if l_message != '{"OldSourceTarget":{' then
                    set l_query = concat(l_query, ',');
                    set l_message = concat(l_message, ',\n');
                end if;

                set l_query = concat(l_query, 'groupId=', p_groupId);
                set l_message = concat(l_message, ',"groupId":', p_groupId);
            end if;

        end if;

    end if;

    select l_errorFlag as errorFlag,
           l_groupId as groupId,
           l_message as message,
           l_errorMessage as errorMessage;
end //

delimiter ;

call createOrUpdateSourceTargets01(null, null, null, null, null, null, null);
call createOrUpdateSourceTargets01(null, 2, null, null, null, null, null);
call createOrUpdateSourceTargets01(null, 2, '', null, null, null, null);
call createOrUpdateSourceTargets01(null, 2, '', null, null, 0, 0);
call createOrUpdateSourceTargets01(null, 2, 'Test One', null, null, 0, 0);
call createOrUpdateSourceTargets01(null, 2, 'Test One', null, null, 1, 1);
call createOrUpdateSourceTargets01(null, 2, 'Test One', null, null, 0, 0);
call createOrUpdateSourceTargets01(null, 2, 'Test Two', null, null, 1, 1);
