use APPSEC_DB;

drop table if exists xmSourceTargets01;

create table xmSourceTargets01 (
    sourceTargetId                         int ( 10 ) unsigned NOT NULL AUTO_INCREMENT,
    name                                   varchar( 64 ) NOT NULL,
    userId                                 int ( 10 ) unsigned NOT NULL,
    groupId                                int ( 10 ) unsigned DEFAULT NULL,
    sequenceId                             int ( 10 ) unsigned NOT NULL,
    isPrimary                              tinyint ( 1 ) unsigned NOT NULL DEFAULT 0,
    enabled                                tinyint ( 1 ) unsigned NOT NULL DEFAULT 0,
    created                                datetime NOT NULL,
    lastUpdated                            datetime NOT NULL,
    PRIMARY KEY ix_sourceTargetId ( sourceTargetId ),
    UNIQUE INDEX ix_userId_name ( userId, name ),
    INDEX ix_userId_groupId ( userId, groupId ),
    UNIQUE INDEX ix_userId_sequence ( userId, sequenceId ),
    INDEX ix_userId_groupId_isPrimary ( userId, groupId, isPrimary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

drop table if exists xmSourceTargetLogs01;

create table xmSourceTargetLogs01 (
    logId                                  int ( 10 ) unsigned NOT NULL AUTO_INCREMENT,
    sourceTargetId                         int ( 10 ) unsigned NOT NULL,
    log                                    varchar ( 8192 ) NOT NULL,
    created                                datetime NOT NULL,
    PRIMARY KEY ix_logId ( logId ),
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

    declare l_errorFlag                      tinyint ( 1 ) unsigned default 0;
    declare l_errorMessage                   varchar( 512 ) default null;
    declare l_query                          varchar( 1024 ) default null;
    declare l_message                        varchar( 1024 ) default '';

    declare l_sourceTargetId                 int ( 10 ) unsigned default null;
    declare l_element                        varchar( 1024 );
    declare l_groupId                        int ( 10 ) unsigned default p_groupId;
    declare l_sequenceId                     int ( 10 ) unsigned default p_sequenceId;
    declare l_sourceTargetGroupId            int ( 10 ) unsigned;
    declare l_id                             int ( 10 ) unsigned default null;
    declare l_isPrimary                      tinyint ( 1 ) unsigned;
    declare l_enabled                        tinyint ( 1 ) unsigned;

    set l_isPrimary = ifnull(p_isPrimary, 0);
    set l_enabled = ifnull(p_enabled, 0);

    -- 1. First, check if a blank user has come in - we need a valid userId
    if p_userId is null or p_userId = 0 then
        set l_errorFlag = 1;
        set l_errorMessage = concat('userId parameter has invalid value: ', ifnull(p_userId, ' null'));

    -- 2. Next, check if a blank name has come in for a new request
    elseif p_name is null or p_name = '' then
        -- Blank or null user names are not allowed for new inserts, check for null p_sourceTargetId
        if p_sourceTargetId is null then
            set l_errorFlag = 1;
            set l_errorMessage = concat('Incorrect value provided for name parameter, found: ', ifnull(p_name, 'null'));
        end if;

    else
        -- 3. We have a valid p_name with text, check if this exists prior for the same user
        select sourceTargetId into l_id from xmSourceTargets01
            where name = p_name and userId = p_userId;

        -- This means, we found an existing name with a different ID for the same user
        if l_id is not null and
            ((p_sourceTargetId is null and l_id is not null) or
               (p_sourceTargetId is not null and p_sourceTargetId != l_id)) then
            set l_errorFlag = 1;
            set l_errorMessage = concat('Found another sourceTarget row that has the same name, with ID: ', l_id);
        end if;
    end if;

    if l_id is not null then
        set l_id = null;
    end if;

    -- 4. Check if we have a different userId coming in, not bound to original sourceTarget
    if l_errorFlag = 0 and p_sourceTargetId is not null then
        select userId into l_id from xmSourceTargets01
            where sourceTargetId = p_sourceTargetId;

        -- Attempt to assign incorrect user to a sourceTargetId
        if l_id != p_userId then
            set l_errorFlag = 1;
            set l_errorMessage = concat('UserId: ', l_id, ' is bound to existing sourceTargetId: ', p_sourceTargetId,
               '. Not relevant for passed userId: ', p_userId);
        end if;
    end if;

    if l_id is not null then
        set l_id = null;
    end if;

    -- 5. Check for an attempt to bring in a groupId absent for this user
    if l_errorFlag = 0 and p_groupId is not null then
        select sourceTargetId into l_id from xmSourceTargets01 where
            userId = p_userId and groupId = p_groupId
            order by sourceTargetId limit 1;

        if l_id is null then
            set l_errorFlag = 1;
            set l_errorMessage = concat('UserId: ', p_userId, ' does not have groupId: ', p_groupId);
        else
            set l_id = null;
        end if;
    end if;

    -- 6. Lastly, check for an attempt to bring in a sequence that already exists
    if l_errorFlag = 0 and p_sequenceId is not null then
        select sourceTargetId into l_id from xmSourceTargets01 where
            userId = p_userId and sequenceId = p_sequenceId;

        if l_id is not null then
            set l_errorFlag = 1;
            set l_errorMessage = concat('UserId: ', p_userId, ' already has sequenceId: ',
                                     p_sequenceId, ' for sourceTargetId: ', l_id);

            set l_id = null;
        end if;
    end if;

    -- 7. Last is a contrived check for group assignment with primary modification for existing entries
    -- Here is the rule-book:
    if l_errorFlag = 0 and p_isPrimary is not null then
        if p_sourceTargetId is not null and p_sourceTargetId > 0 then
            if p_groupId is null then
                select groupId into l_groupId from
                    xmSourceTargets01 where sourceTargetId = p_sourceTargetId;

                if l_groupId is not null then
                    if p_isPrimary = 1 then
                        -- Reset existing isPrimary to 0, as we have the incoming candidate as the new primary for our group
                        update xmSourceTargets01 set isPrimary = 0, lastUpdated = utc_timestamp()
                            where userId = p_userId and groupId = l_groupId and isPrimary = 1;
                    else
                       -- Else, we have isPrimary = 0. If we reset the existing one to non-primary, nominate the first one as primary
                       -- Find the first willing candidate
                       select sourceTargetId into l_id from xmSourceTargets01
                           where groupId = l_groupId and sourceTargetId != p_sourceTargetId
                           and isPrimary = 0
                           order by sourceTargetId limit 1;

                       if l_id != null then
                           update xmSourceTargets set isPrimary = 1, lastUpdated = utc_timestamp()
                              where sourceTargetId = l_id;

                           set l_id = null;
                       end if;
                    end if;
                end if;

            elseif p_groupId > 0 then
                if p_isPrimary = 1 then
                    update xmSourceTargets01 set isPrimary = 0 where userId = p_userId
                        and groupId = p_groupId and isPrimary = 1;
                else
                    -- This is for p_isPrimary = 0
                    select sourceTargetId into l_id from xm_sourceTargets
                    where groupId = p_groupId and isPrimary = 0
                    and sourceTargetId != p_sourceTargetId
                    order by Id limit 1;

                    if l_id != null then
                        update xmSourceTargets set isPrimary = 1, lastUpdated = utc_timestamp()
                            where sourceTargetId = l_id;

                        set l_id = null;
                    end if;
                end if;

            elseif p_groupId = 0 and p_isPrimary = 1 then
                -- This is the scenario where we have an unhook happening but the user still wants this entry to be primary of a newly forming group
                set l_groupId = null;

                -- Find the existing entry for this groupId that may have isPrimary
                -- Find last group ID
                select groupId into l_groupId from xmSourceTargets01
                    where userId = p_userId
                    order by groupId desc limit 1;

                -- If none existed, set 1 or else increment by 1
                if l_groupId is null then
                    set l_groupId = 1;
                else
                    set l_groupId = l_groupId + 1;
                end if;
            end if;

        elseif p_groupId is null then
            set l_groupId = null;

            -- Find last group ID
            select groupId into l_groupId from xmSourceTargets01
                where userId = p_userId
                order by groupId desc limit 1;

            -- If none existed, set 1 or else increment by 1
            if l_groupId is null then
                set l_groupId = 1;
            else
                set l_groupId = l_groupId + 1;
            end if;
        else
            -- Reset primary for the old candidate that had this for our group
            update xmSourceTargets01 set isPrimary = 0
                where userId = p_userId and
                groupId = p_groupId
                and isPrimary = 1;
        end if;
    end if;

    -- No errors found
    if l_errorFlag = 0 then
        -- Attempt to create a new sourceTarget, check for nullability or 0 value for p_sourceTargetId
        if p_sourceTargetId = 0 or p_sourceTargetId is null then
            -- We have a blank sequenceId, generate a new one, 1 more than existing one for this user
            -- Remember, we have no relation between groups and sequences
            if l_sequenceId is null then
                select sequenceId into l_sequenceId from xmSourceTargets01
                    where userId = p_userId order by sequenceId desc limit 1;

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
                userId,
                groupId,
                sequenceId,
                isPrimary,
                enabled,
                created,
                lastUpdated
            ) values (
                p_name,
                p_userId,
                l_groupId,
                l_sequenceId,
                l_isPrimary,
                l_enabled,
                utc_timestamp(),
                utc_timestamp()
            );

            select last_insert_id() into l_sourceTargetId;

            set l_message = concat('{"NewSourceTarget":{\n',
                                '"name":"', replace(p_name, '"', '\\"'), '",\n',
                                '"userId":', p_userId, ',\n',
                                '"groupId":', ifnull(l_groupId, 'null'), ',\n',
                                '"isPrimary":', l_isPrimary, ',\n',
                                '"sequenceId":', l_sequenceId, ',\n',
                                '"enabled":', l_enabled, '}}');

            insert xmSourceTargetLogs01 (
                sourceTargetId, log, created
            ) values (
                l_sourceTargetId, l_message, utc_timestamp()
            );

        else
            -- Attempt being made to update an existing sourceTarget for funds
            set l_query = 'update xmSourceTargets01 set ';
            set l_message = '{"OldSourceTarget":{';

            -- There is a different name being furnished
            if p_name is not null and p_name != '' then

                set l_query = concat(l_query, 'name=');

                set l_element = replace(p_name, '\'', '\'\'');
                set l_query = concat(l_query, '\'', l_element, '\'');
                set l_message = concat(l_message, '"name":"', replace(l_element, '"', '\\"'), '"');
            end if;

            if p_groupId is not null then
                if p_groupId = 0 and (l_groupId = 0 or l_groupId is null) then
                    set l_groupId = null;
                end if;

                if l_message != '{"OldSourceTarget":{' then
                    set l_query = concat(l_query, ',');
                    set l_message = concat(l_message, ',\n');
                end if;

                set l_query = concat(l_query, 'groupId=', l_groupId);
                set l_message = concat(l_message, ',"groupId":', l_groupId);
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

            if l_query != 'update xmSourceTargets01 set ' then
                set l_query = concat(l_query, ',lastUpdated=utc_timestamp() where sourceTargetId=', p_sourceTargetId, ';');
                set l_message = concat(l_message, ',"userId":', p_userId, '}}');

                -- Execute the query!
--              set @statement = l_query;
--              prepare stmt from @statement;
--              execute stmt;
--              deallocate prepare stmt;

                insert xmSourceTargetLogs01 (
                    sourceTargetId, log, created
                ) values (
                    p_sourceTargetId, l_message, utc_timestamp()
                );
            end if;
        end if;
    end if;

    if l_errorFlag > 0 then
        insert xmSourceTargetLogs01 (
            sourceTargetId, log, created
        ) values (
            0, l_errorMessage, utc_timestamp()
        );
    end if;

    select l_errorFlag as errorFlag,
           l_sourceTargetId as sourceTargetId,
           l_groupId as groupId,
           l_message as message,
           l_query as query,
           l_errorMessage as errorMessage;
end //

delimiter ;
*/
-- call createOrUpdateSourceTargets01(null, null, null, null, null, null, null);
-- call createOrUpdateSourceTargets01(null, 0, null, null, null, null, null);
-- call createOrUpdateSourceTargets01(null, 2, null, null, null, null, null);
-- call createOrUpdateSourceTargets01(null, 2, '', null, null, null, null);
-- call createOrUpdateSourceTargets01(null, 2, 'A', null, null, null, null);
-- call createOrUpdateSourceTargets01(null, 2, 'A', null, null, null, null);
-- call createOrUpdateSourceTargets01(null, 2, 'A"', null, null, null, null);
-- call createOrUpdateSourceTargets01(null, 2, 'A\'', null, null, null, null);
-- call createOrUpdateSourceTargets01(null, 2, 'B', 1, null, null, null);
-- call createOrUpdateSourceTargets01(1, 3, 'B', null, null, null, null);
-- call createOrUpdateSourceTargets01(null, 2, 'B', null, 1, null, null);
-- call createOrUpdateSourceTargets01(null, 2, 'B', null, null, 1, null);
-- call createOrUpdateSourceTargets01(null, 2, 'C', 1, null, 1, null);
-- call createOrUpdateSourceTargets01(5, 2, null, null, null, null, null);
-- call createOrUpdateSourceTargets01(5, 2, 'A', null, null, null, null);
-- call createOrUpdateSourceTargets01(5, 2, 'D', null, null, null, null);
-- call createOrUpdateSourceTargets01(5, 2, 'D', 1, null, null, null);
-- call createOrUpdateSourceTargets01(5, 2, null, 1, null, null, null);
-- call createOrUpdateSourceTargets01(5, 2, null, 1, 1, null, null);
-- call createOrUpdateSourceTargets01(5, 2, null, null, 1, null, null);
-- call createOrUpdateSourceTargets01(5, 2, null, null, 10, null, null);


update xmSourceTargets01 set sequenceId=10,lastUpdated=utc_timestamp() where sourceTargetId=5;
update xmSourceTargets01 set name='D', lastUpdated=utc_timestamp() where sourceTargetId=5;
update xmSourceTargets01 set name='D',groupId=1, lastUpdated=utc_timestamp() where sourceTargetId=5;
update xmSourceTargets01 set groupId=1,lastUpdated=utc_timestamp() where sourceTargetId=5;

-- select * from xmSourceTargetLogs01 order by logId desc;
select * from xmSourceTargets01;

/*
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
    elseif p_name is null or p_name = '' then

        -- Blank or null user names are not allowed for new inserts, check for null p_sourceTargetId
        if p_sourceTargetId is null then
            set l_errorFlag = 1;
            set l_errorMessage = concat('Incorrect value provided for name parameter, found: ', ifnull(p_name, 'null'));
        end if;
    else
        -- We have a valid p_name with text, check if this exists prior for the same user
        select sourceTargetId into l_sourceTargetId from xmSourceTargets01
            where name = p_name and userId = p_userId;

        -- This means, we found an existing name with a different ID for the same user
        if l_sourceTargetId is not null and
            ((p_sourceTargetId is null and l_sourceTargetId is not null) or
               (p_sourceTargetId is not null and p_sourceTargetId != l_sourceTargetId)) then

            set l_errorFlag = 1;
            set l_errorMessage = concat('Found another sourceTarget row with same name, with ID: ', l_sourceTargetId);
        end if;
    end if;

    -- 4. Check if we have a different userId coming in, not bound to original sourceTarget
    if l_errorFlag = 0 and p_sourceTargetId is not null then

        select userId into l_id from xmSourceTargets01 where sourceTargetId = p_sourceTargetId;

        -- Attempt to assign incorrect user to a sourceTargetId
        if l_id != p_userId then

            set l_errorFlag = 1;
            set l_errorMessage = concat('UserId: ', l_id, ' is bound to existing sourceTargetId: ', l_sourceTargetId,
               '. Not relevant for passed userId: ', p_userId);
        end if;
    end if;

    -- 5. Check for an attempt to bring in a groupId absent for this user
    if l_errorFlag = 0 and p_groupId is not null then

        set l_id = null;

        select sourceTargetId into l_id from xmSourceTargets01 where
            userId = p_userId and groupId = p_groupId
            order by sourceTargetId limit 1;

        if l_id is null then

            set l_errorFlag = 1;
            set l_errorMessage = concat('UserId: ', p_userId, ' does not have groupId: ', p_groupId);

        end if;
    end if;

    select l_errorFlag as errorFlag,
           l_groupId as groupId,
           l_message as message,
           l_query as query,
           l_errorMessage as errorMessage;

end //

delimiter ;
*/
call createOrUpdateSourceTargets01 (
null,
2,
'A',
null,
null,
null,
null );
/*
insert xmSourceTargets01 (
    name,
    userId,
    groupId,
    sequenceId,
    isPrimary,
    enabled,
    created,
    lastUpdated
)
values (
 'A',
 2,
 null,
 1,
 0,
 1,
 utc_timestamp(),
 utc_timestamp()
);


-- select * from xmSourceTargets01;
call createOrUpdateSourceTargets01(null, null, null, null, null, null, null);

call createOrUpdateSourceTargets01(null, 2, null, null, null, null, null);
call createOrUpdateSourceTargets01(null, 2, '', null, null, null, null);
call createOrUpdateSourceTargets01(null, 2, '', null, null, 0, 0);

call createOrUpdateSourceTargets01(null, 2, 'Test One', null, null, 0, 1);
call createOrUpdateSourceTargets01(null, 2, 'Test Two', null, null, 1, 1);
call createOrUpdateSourceTargets01(null, 2, 'Test Three', 1, null, 0, 1);

-- call createOrUpdateSourceTargets01(null, 2, 'Test Three', null, null, 1, 1);

call createOrUpdateSourceTargets01(null, 2, 'Test Two', null, null, 1, 1);

-- call createOrUpdateSourceTargets01(null, 2, 'Test One', null, null, 0, 0);

-- select * from xmSourceTargets01;
*/

-- call createOrUpdateSourceTargets01(null, 2, 'Foo', 2, null, 0, 0);

-- select * from xmSourceTargetChangeLogs01;
