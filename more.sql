drop table if exists table01;

create table table01 (
    id                                     int ( 10 ) unsigned NOT NULL AUTO_INCREMENT,
    name                                   varchar( 64 ) NOT NULL,
    PRIMARY KEY ix_id ( id ),
    INDEX ix_name ( name )
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

drop table if exists table02;

create table table02 (
    id                                     int ( 10 ) unsigned NOT NULL AUTO_INCREMENT,
    table01Id                              int ( 10 ) unsigned NOT NULL,
    name                                   varchar( 64 ) DEFAULT NULL,
    PRIMARY KEY ix_Id ( id ),
    INDEX ix_name ( name )
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

update table01 t01 inner join table02 t02
on t01.id = t02.table01Id
set t02.name = 'Ek' where t01.name = 'One';


drop table if exists xmSourceTargets01;

create table xmSourceTargets01 (
    sourceTargetId                         int ( 10 ) unsigned NOT NULL AUTO_INCREMENT,
    name                                   varchar( 64 ) NOT NULL,
    sequenceId                             int ( 10 ) unsigned NOT NULL,
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
    sourceTargetId                         int ( 10 ) unsigned NOT NULL,
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
    UNIQUE INDEX ix_userId_sourceTargetId ( userId, sourceTargetId )
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

drop table if exists xmSourceTargetChangeLogs01;

create table xmSourceTargetChangeLogs01 (
    logId                                  int ( 10 ) unsigned NOT NULL AUTO_INCREMENT,
    sourceTargetId                         int ( 10 ) unsigned NOT NULL,
    userId                                 int ( 10 ) unsigned NOT NULL,
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

    declare l_errorFlag                      tinyint ( 1 ) unsigned;
    declare l_errorMessage                   varchar( 512 );
    declare l_query                          varchar( 1024 );
    declare l_message                        varchar( 1024 );

    declare l_sourceTargetId                 int ( 10 ) unsigned;
    declare l_element                        varchar( 1024 );
    declare l_groupId                        int ( 10 ) unsigned;
    declare l_sequenceId                     int ( 10 ) unsigned;
    declare l_sourceTargetGroupId            int ( 10 ) unsigned;
    declare l_insertFlag                     tinyint ( 1 ) unsigned; 
    declare l_id                             int ( 10 ) unsigned;

    set l_message = '';
    set l_errorMessage = null;
    set l_sourceTargetId = null;
    set l_groupId = p_groupId;
    set l_sequenceId = p_sequenceId;
    set l_id = null;
    set l_insertFlag = 0;

    -- 1. First, check if a blank user has come in - we need a valid userId
    if p_userId is null or p_userId = 0 then

        set l_errorFlag = 1;
        set l_errorMessage = concat('userId parameter has invalid value: ', ifnull(p_userId, ' null'));

    -- 2. Next, check if a blank name has come in for a new request
    elseif p_sourceTargetId is null and (p_name is null or p_name = '') then

        set l_errorFlag = 1;
        set l_errorMessage = concat('Incorrect value provided for name parameter, found: ', ifnull(p_name, 'null'));

    -- 3. Then check for names that might already exist for this user in the database
    elseif p_name is not null and p_name != '' then

        select xST.sourceTargetId into l_sourceTargetId from xmSourceTargets01 xST
            inner join xmSourceTargetMapping01 xSTM on xST.sourceTargetId = xSTM.sourceTargetId
            where xST.name = p_name and xSTM.userId = p_userId;

        -- This means, we don't have a prior occurence of this name - all is well
        if l_sourceTargetId is null then
            set l_errorFlag = 0;
        -- Else we found an existing item with a different ID
        elseif (p_sourceTargetId is null and l_sourceTargetId is not null) or
               (p_sourceTargetId is not null and p_sourceTargetId != l_sourceTargetId) then
            set l_errorFlag = 1;
            set l_errorMessage = concat('Found another sourceTarget row with same name, with ID: ', l_sourceTargetId);
        end if;

        -- 4. Check if we have a different userId coming in, not bound to original sourceTarget
        if p_sourceTargetId is not null then
            select userId into l_id from xmSourceTargetMapping01 where sourceTargetId = l_sourceTargetId;

            -- Attempt to assign incorrect user to a sourceTargetId
            if l_id != p_userId then

                set l_errorFlag = 1;
                set l_errorMessage = concat('UserId: ', l_id, ' is bound to existing sourceTargetId: ', l_sourceTargetId,
                   '. Not relevant for passed userId: ', p_userId);

            -- 5. Check for an attempt to bring in a groupId absent for this user
            elseif p_groupId is not null then

                set l_id = null;

                select sourceTargetGroupId into l_id from xmSourceTargetGroups01 where
                    userId = p_userId and groupId = p_groupId;

                if l_id is null then

                    set l_errorFlag = 1;
                    set l_errorMessage = concat('UserId: ', p_userId, ' does not have groupId: ', p_groupId);

                end if;
            end if;
        end if;

    end if;

    -- Reset l_id if already set in any lookups prior
    if l_id is not null then
        set l_id = null;
    end if;

    -- No errors found
    if l_errorFlag = 0 then

        -- Attempt to create a new sourceTarget
        if p_sourceTargetId = 0 or p_sourceTargetId is null then

            -- Seeking a primary assignment
            if p_isPrimary = 1 then

                -- But no groupId specified, find next one that we can employ for this user
                if (p_groupId is null or p_groupId = 0) then

                    -- Find last group ID
                    select groupId into l_groupId from xmSourceTargetGroups01
                    where userId = p_userId
                    order by groupId desc limit 1;

                    -- If none existed, set 1 or else increment by 1
                    if l_groupId is null then
                        set l_groupId = 1;
                    else
                        set l_groupId = l_groupId + 1;
                    end if;

                    -- Set l_insertFlag to 1 - we need to create a new sourceTargetGroup
                    set l_insertFlag = 1;
                else
                    -- Set l_insertFlag to 2 - we need to modify the sourceTargetFlag for this groupId
                    -- and set isPrimary = 0 for the existing primary sourceTarget in this group
                    set l_insertFlag = 2;
                end if;

            end if;

            -- We have a blank sequenceId, generate a new one, 1 more than existing one for this user
            -- Remember, we have no relation between groups and sequences
            if l_sequenceId is null then
                select xST.sequenceId into l_sequenceId from xmSourceTargets01 xST
                inner join xmSourceTargetMapping01 xSTM on xST.sourceTargetId = xSTM.sourceTargetId
                where xSTM.userId = p_userId order by xST.sequenceId desc limit 1;

                -- We did not find any prior sequence, start with 1, else increment by 1
                if l_sequenceId is null then
                    set l_sequenceId = 1;
                else
                    set l_sequenceId = l_sequenceId + 1;
                end if;
            end if;

            -- Create new sourceTarget row, get the sourceTargetId generated
            insert xmSourceTargets01 (
                name,
                sequenceId,
                isPrimary,
                enabled,
                created,
                lastUpdated
            ) values (
                p_name,
                l_sequenceId,
                p_isPrimary,
                p_enabled,
                utc_timestamp(),
                utc_timestamp()
            );

            select last_insert_id() into l_sourceTargetId;

            -- Insert the mapping that binds it to this user
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
                sourceTargetId, userId, log, created
            ) values (
                l_sourceTargetId, p_userId, l_message, utc_timestamp()
            );

            if l_insertGroup = 1 then

                insert xmSourceTargetGroups01 (
                    sourceTargetId,
                    groupId,
                    userId,
                    name,
                    enabled,
                    created,
                    lastUpdated
                ) values (
                    l_sourceTargetId,
                    l_groupId,
                    l_userId,
                    p_name,
                    1,
                    utc_timestamp(),
                    utc_timestamp()
                );

                select last_insert_id() into l_sourceTargetGroupId;

                set l_message = concat('{"NewSourceTargetGroup":{\n',
                                    '"sourceTargetGroupId":', l_sourceTargetGroupId, ',\n',
                                    '"name":"', replace(p_name, '"', '\"'), '",\n',
                                    '"userId":', p_userId, ',\n',
                                    '"groupId":', l_groupId, '}}');

                insert xmSourceTargetChangeLogs01 (
                        sourceTargetId, userId, log, created
                    ) values (
                        l_sourceTargetId, p_userId, l_message, utc_timestamp()
                    );

            elseif l_insertGroup = 2 then

                -- Find existing row in xmSourceTargetGroups01 where isPrimary = 1 for same group
                select sourceTargetId into l_id from xmSourceTargetGroups01 where
                userId = p_userId and groupId = p_groupId;

                if l_id is not null then
                    -- First off, update an existing row in xmSourceTargets01 that has isPrimary = 1 for same row
                    update xmSourceTargets01 set isPrimary = 0 where
                    sourceTargetId = l_id;

                    -- Update row in xmSourceTargetGroups where we are refering to original group that is having
                    -- a primary change
                    update xmSourceTargetGroups01 set sourceTargetId = l_sourceTargetId where
                    groupId = p_groupId and userId = p_userId;

                    set l_message = concat('{"OldSourceTargetGroup":{\n',
                                '"userId":', p_userId, ',\n',
                                '"groupId":', p_groupId, ',\n',
                                '"oldPrimarySourceTargetId":', l_id, ',\n',
                                '"newPrimarySourceTargetId":', l_sourceTargetId, '}}');

                    insert xmSourceTargetChangeLogs01 (
                            sourceTargetId, userId, log, created
                        ) values (
                            l_sourceTargetId, p_userId, l_message, utc_timestamp()
                        );

                end if;
            end if;

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

            if p_isPrimary is not null then

                if l_message != '{"OldSourceTarget":{' then
                    set l_query = concat(l_query, ',');
                    set l_message = concat(l_message, ',\n');
                end if;

                set l_query = concat(l_query, 'isPrimary=', p_isPrimary);
                set l_message = concat(l_message, ',"isPrimary":', p_isPrimary);
            end if;

            if p_sequenceId is not null then

                if l_message != '{"OldSourceTarget":{' then
                    set l_query = concat(l_query, ',');
                    set l_message = concat(l_message, ',\n');
                end if;

                set l_query = concat(l_query, 'sequenceId=', p_sequenceId);
                set l_message = concat(l_message, ',"sequenceId":', p_sequenceId);
            end if;

            if p_enabled is not null then

                if l_message != '{"OldSourceTarget":{' then
                    set l_query = concat(l_query, ',');
                    set l_message = concat(l_message, ',\n');
                end if;

                set l_query = concat(l_query, 'enabled=', p_enabled);
                set l_message = concat(l_message, ',"enabled":', p_enabled);
            end if;

            if l_query = 'update xmSourceTargets01 set ' and l_message = '{"OldSourceTarget":{' then
                set l_message = '';
            elseif l_query != 'update xmSourceTargets01 set ' then
                set l_query = concat(l_query, ' lastUpdated = utc_timestamp() where sourceTargetId = '", p_sourceTargetId, ';');
                set l_message = concat(l_message, '}}');

                set @statement = l_query;
                prepare stmt from @statement;
                execute stmt;
                deallocate prepare stmt;

            end if;
        end if;

    end if;

    if l_message != '' then

        insert xmSourceTargetChangeLogs01 (
            sourceTargetId, userId, log, created
        ) values (
            l_sourceTargetId, p_userId, l_message, utc_timestamp()
        );

    end if;

    if l_errorFlag > 0 then
        insert xmSourceTargetChangeLogs01 (
            sourceTargetId, userId, log, created
        ) values (
            0, 1, l_errorMessage, utc_timestamp()
        );

    end if;

    select l_errorFlag as errorFlag,
           l_groupId as groupId,
           l_message as message,
           l_query as query,
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
