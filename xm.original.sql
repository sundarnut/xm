-- ****************************** Module Header ******************************
-- Module Name:  xm website SQL file
-- Project:      xm website to track everyday expenses
--
-- xm.sql file to create the database, load metadata tables and stored procedures - please employ MySQL 5.5 or greater
--
--    1.   T1: users table - stores folks that can access this application
--    2.   T2: activityLogs table - creations, updates or deletes, anything that changes for a user in the system
--    3.   P1: addConstraint stored procedure - add a constraint from users table to activityLogs table
--    4.   T3: emailUserLog table - all individual email addresses validated by a user go here
--    5.   P2: addFirstUser stored procedure - add first user to users table
--    6.   T4: loginAttempts table - loginAttempts tracks logins happening, and those that are failed/locked
--    7.   T5. secretQuestions table - set of standard questions that we need to make user select one out of
--    8.   P3. populateSecretQuestions stored procedure - populate a set of secret questions for this user to answer
--    9.   P4. getSecretQuestions stored procedure - get a set of secret questions for this user to answer
--   10.   T6. userSecretQuestions table - a table to store individual secret question answer hashes for this user (in lowercase)
--   11.   P5. updateSecretQuestion stored procedure - update a secret question and answer for this user
--   12.   P6. getSecretQuestionData stored procedure - obtain the secret question and answer for a user that she had chosen before
--   13.   P7. getUserData stored procedure - get user data for the username (login or email address) supplied
--   14.   T7. appSettings table - all system-level settings for app
--   15.   T8. appSettingsLog table - what are all the changes we are making to appSettings table?
--   16.   P8. getSettings stored procedure - get all the settings saved in this table
--   17.   P9. getSetting stored procedure - get the appSettings value for the incoming name
--   18.  P10. updateSetting stored procedure - add or update a setting name and value
--   19.  P11. getEnabledSettings() stored procedure - get name/value pairs of settings that are enabled
--   20.  P12. populateSettings stored procedure - populate known settings for the application
--   21.   T9. accessLogs table - store metadata for any request to our application
--   22.  P13. logUserAccess stored procedure - log the access attempt made by a client/user
--   23.  P14. updateTimeZone stored procedure - update the timezone record for the corresponding session key
--   24.  P15. updateUser stored procedure - update user data
--   25.  P16. addFirstRealUser stored procedure - add first real user for this application
--   26.  P17. populateSecretQuestionDataForFirstRealUser stored procedure - add data to corroborate first real user for this application
--   27.  T10. sessionData table - to store session data in cases where Apache sessions fail us
--   28.  P18. getSession stored procedure - to fetch data for a session variable previously stored
--   29.  P19. setSession stored procedure - to save data into the sessionData table for a name-value pair
--   30.  T11. mailApiKeys table to store API keys that anyone can employ to send email
--   31.  P20. populateApiKey stored procedure to add the first API key we will employ to call the web service
--   32.  P21. checkMailApiKey stored procedure to check if the furnished API key has a valid active flag
--   33.  T12. mails table to store emails that need to be dispatched
--   34.  T13. mailAttachments table to store attachment data for certain emails
--   35.  T14. mailsSent table to log all the emails we successfully dispatch via scheduling
--   36.  P22. addEmail stored procedure to add new email to mails table, mail would not be dispatched unless all attachments are in too
--   37.  P23. addMailAttachment stored procedure to add one attachment to a previously saved email message
--   38.  P24. markEmailAsReady stored procedure to mark email as ready to send
--   39.  P25. getEmailToSend stored procedure to get the next email to dispatch, hasAttachments would tell you if you need to call getAttachmentsForEmail
--   40.  P26. getAttachmentsForEmail stored procedure to get attachments that are defined or were added for this email, assuming ready is still set to false for this mailId
--   41.  P27. getAttachmentsForEmail stored procedure to delete this email, we have successfully dispatched it into the ether
--   42.  P28. logEmailDispatch stored procedure to log the use case where we have successfully sent a mail

--
-- Revisions:
--      1. Sundar Krishnamurthy         sundar_k@hotmail.com               06/10/2017       Initial file created.
--      2. Sundar Krishnamurthy         sundar_k@hotmail.com               10/04/2017       Added sessionData table, comments


-- Very, very, very bad things happen if you uncomment this line below. Do at your peril, you have been warned!
-- drop database if exists $$DATABASE_NAME$$;                                                        -- $$ DATABASE_NAME $$

-- Create database $$DATABASE_NAME$$, with utf8 and utf8_general_ci
create database if not exists $$DATABASE_NAME$$ character set utf8 collate utf8_general_ci;       -- $$ DATABASE_NAME $$

-- Employ $$DATABASE_NAME$$
use $$DATABASE_NAME$$;                                                                            -- $$ DATABASE_NAME $$

-- drop table if exists users;

--    1.   T1. users table stores folks that can access this application
create table if not exists users (
    userId                                    int ( 10 ) unsigned not null auto_increment,
    username                                  varchar( 32 ) default null,                       -- Unique, cannot be changed
    firstName                                 varchar( 32 ) not null,
    lastName                                  varchar( 32 ) default null,
    firstLastName                             varchar( 65 ) not null,
    lastFirstName                             varchar( 65 ) not null,
    email                                     varchar( 128 ) not null,                           -- Unique, big rules exist for changing thia
    salt                                      varchar( 32 ) not null,                            -- Unique, does not change
    password                                  varchar( 64 ) not null,
    userKey                                   varchar( 32 ) not null,                            -- Unique, does not change
    otpKey                                    int ( 6 ) unsigned default null,
    accessKey                                 varchar( 32 ) default null,
    active                                    tinyint ( 1 ) unsigned not null default 0,
    exclude                                   tinyint ( 1 ) unsigned not null default 0,
    status                                    int ( 10 ) unsigned not null default 0,
    notificationMask                          tinyint ( 2 ) unsigned not null default 0,
    comments                                  varchar( 512 ) default null,
    created                                   datetime not null,
    lastUpdate                                datetime not null,
    key ( userId ),
    index ix_firstLastName ( firstLastName ),
    index ix_lastFirstName ( lastFirstName ),
    unique index ix_username ( username ),
    index ix_userKey ( userKey ),
    unique index ix_email ( email )
) engine=innodb default character set=utf8;

-- drop table if exists activityLogs;

--    2.   T2. Activity log for creations or updates or deletes, anything that changes for a user in the system
create table if not exists activityLogs (
    activityId                                int ( 10 ) unsigned not null auto_increment,
    message                                   varchar( 512 ) not null,
    userId                                    int ( 10 ) unsigned not null,
    created                                   datetime not null,
    index  ix_userId                          ( userId ),
    key ( activityId )
) engine=innodb default character set=utf8;

-- Add constraint from activityLogs to users for userId
drop procedure if exists addConstraint;

delimiter //

--    3.   P1. Add the constraint if it does not exist - activity logs to users for userId
create procedure addConstraint()
begin
    -- Add constraint for activityLogs to users
    if not exists (select * from information_schema.TABLE_CONSTRAINTS where
                   CONSTRAINT_SCHEMA = DATABASE() and
                   CONSTRAINT_NAME   = 'fk_activityLogs_users_userId' and
                   CONSTRAINT_TYPE   = 'FOREIGN KEY') then

        alter table
            activityLogs
        add constraint
            fk_activityLogs_ysers_userId
        foreign key (userId)
        references users (userId)
        on update cascade
        on delete cascade;
    end if;
end //

delimiter ;

-- Set the constraint
call addConstraint();

-- You don't need this procedure anymore
drop procedure addConstraint;

-- drop table if exists emailUserLog;

--    4.   T3. All individual email addresses validated by a user go here
create table if not exists emailUserLog (
    logId                                     int ( 10 ) unsigned not null auto_increment,
    userId                                    int ( 10 ) unsigned not null,
    email                                     varchar( 128 ) not null,
    created                                   datetime not null,
    key ( logId ),
    unique index ix_email ( email )
) engine=innodb default character set=utf8;


-- Drop procedure we are about to create, if it exists prior
drop procedure if exists addFirstUser;

-- First user has to be injected manually
delimiter //

--    5.   P2. Add first user to users table
create procedure addFirstUser()
begin
    declare l_userCount                       int ( 10 ) unsigned;

    select count(*) into l_userCount from users;

    if l_userCount = 0 then
        -- Password for System Agent is 72917d59f97d4ee48f82e20ac4e07283 (cannot log in :))
        insert users (username, firstName, lastName, firstLastName, lastFirstName, email, salt,
                      password, userKey, active, status, notificationMask, created, lastUpdate)
        values ('root', 'System Agent', null, 'System Agent', 'System Agent', 'brahma@somesite.com', '2c22e1a5fe294d6db6e24d018b149bc7',
                '628ec4efc1298daadf5d4e5084ab8665c8bc72e41d10527c0247a1b578a9c544', 'd438964f23c14bea9ea94bcfeebe5bb9',
                 0, 0, 0, utc_timestamp(), utc_timestamp());

        insert activityLogs(message, userId, created) values ('{"NewUser":true,"Message":"Brahma is born"}', 1, utc_timestamp());

        insert emailUserLog (userId, email, created) values (1, 'brahma@somesite.com', utc_timestamp());

    end if;

end //

delimiter ;

-- Invoke SP to insert first user
call addFirstUser();

drop procedure addFirstUser;

delimiter ;

-- drop table if exists loginAttempts;

--    6.   T4. loginAttempts tracks logins happening, and those that are failed/locked
create table if not exists loginAttempts (
    logId                                     int ( 10 ) unsigned not null auto_increment,
    sessionKey                                varchar( 32 ) not null,
    userCredentials                           varchar( 128 ) default null,
    failed                                    tinyint ( 1 ) unsigned default 0,
    alternatePage                             tinyint ( 1 ) unsigned default 0, -- User attempted from another page, not login.php
    created                                   datetime not null,
    key ( logId )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;

-- drop table if exists secretQuestions;

--    7.   T5. secretQuestions table - set of standard questions that we need to make user select one out of
create table if not exists secretQuestions (
    questionId                                int ( 10 ) unsigned not null auto_increment,
    question                                  varchar( 128 ) not null,
    enabled                                   tinyint( 1 ) unsigned not null default 0,
    sequence                                  tinyint( 1 ) unsigned not null,
    created                                   datetime not null,
    key ( questionId )
) engine=innodb default character set=utf8;

drop procedure if exists populateSecretQuestions;

delimiter //

--    8.   P3. Populate a set of secret questions for this user to answer
create procedure populateSecretQuestions()
begin

    declare l_count                           int ( 10 ) unsigned;

    select count(*) into l_count from secretQuestions;

    if l_count = 0 then
        insert secretQuestions(question, enabled, sequence, created)
            values('Where did your mother and father meet each other for the first time', 1, 1, utc_timestamp());
        insert secretQuestions(question, enabled, sequence, created)
            values('What is the first thing that you would buy if you won a million dollars', 1, 2, utc_timestamp());
        insert secretQuestions(question, enabled, sequence, created)
            values('What is the university or graduate school that you would\'ve loved to attend', 1, 3, utc_timestamp());
        insert secretQuestions(question, enabled, sequence, created)
            values('What is the name of the song that you love the most', 1, 4, utc_timestamp());
        insert secretQuestions(question, enabled, sequence, created)
            values('What is the name of the street that you grew up on', 1, 5, utc_timestamp());
        insert secretQuestions(question, enabled, sequence, created)
            values('What is the name of your favorite niece or nephew', 1, 6, utc_timestamp());
        insert secretQuestions(question, enabled, sequence, created)
            values('What is your maternal grandmother\'s first name', 1, 7, utc_timestamp());
        insert secretQuestions(question, enabled, sequence, created)
            values('What is your paternal grandmother\'s first name', 1, 8, utc_timestamp());
        insert secretQuestions(question, enabled, sequence, created)
            values('Who is the historical figure or personality that you would\'ve loved to meet the most', 1, 9, utc_timestamp());
        insert secretQuestions(question, enabled, sequence, created)
            values('What is the city you\'ve visited, that you loved the most', 1, 10, utc_timestamp());
        insert secretQuestions(question, enabled, sequence, created)
            values('What is the name of the first elementary school that you attended', 1, 11, utc_timestamp());
        insert secretQuestions(question, enabled, sequence, created)
            values('What is your favorite security question', 1, 12, utc_timestamp());
    end if;

end //

delimiter ;

call populateSecretQuestions();

drop procedure populateSecretQuestions;

drop procedure if exists getSecretQuestions;

delimiter //

--    9.   P4. getSecretQuestions stored procedure - get a set of secret questions for this user to answer
create procedure getSecretQuestions()
begin

    select
        questionId,
        question
    from
        secretQuestions
    where
        enabled = 1
    order by
        sequence;
end //

delimiter ;

-- drop table if exists userSecretQuestions;

--   10.   T6. userSecretQuestions table - a table to store individual secret question answer hashes for this user (in lowercase)
create table if not exists userSecretQuestions (
    userQuestionId                            int ( 10 ) unsigned not null auto_increment,
    userId                                    int ( 10 ) unsigned not null,
    sequenceId                                tinyint ( 1 ) unsigned not null,
    questionId                                int ( 10 ) unsigned not null,
    answerHash                                varchar( 64 ) not null,
    created                                   datetime not null,
    lastUpdate                                datetime not null,
    key ( userQuestionId ),
    unique index (userId, sequenceId)
) engine=innodb default character set=utf8;

drop procedure if exists updateSecretQuestion;

delimiter //

--   11.   P5. Update a secret question and answer for this user
create procedure updateSecretQuestion(
   in p_userId                                int ( 10 ) unsigned,
   in p_questionId                            int ( 10 ) unsigned,
   in p_sequenceId                            int ( 10 ) unsigned,
   in p_answerHash                            varchar( 64 ),
   in p_suppressFlag                          tinyint ( 1 ) unsigned
)
begin

    declare l_userQuestionId                  int ( 10 ) unsigned;
    declare l_questionId                      int ( 10 ) unsigned;
    declare l_question                        varchar( 128 );
    declare l_answerHash                      varchar( 64 );

    set l_userQuestionId = null;

    start transaction;

    select userQuestionId, questionId, answerHash
    into l_userQuestionId, l_questionId, l_answerHash
    from userSecretQuestions
    where userId = p_userId
    and sequenceId = p_sequenceId
    for update;

    select question into l_question from secretQuestions
    where questionId = p_questionId;

    set l_question = replace(l_question, '"', '\\"');

    if l_userQuestionId is null then
        insert userSecretQuestions (
            userId,
            questionId,
            sequenceId,
            answerHash,
            created,
            lastUpdate
        ) values (
            p_userId,
            p_questionId,
            p_sequenceId,
            p_answerHash,
            utc_timestamp(),
            utc_timestamp()
        );

        select last_insert_id() into l_userQuestionId;

        insert activityLogs (
            userId,
            message,
            created
        ) values (
            p_userId,
            concat('{"Update":{"SequenceId":',
                    p_sequenceId,
                    '\n,"QuestionId":',
                    p_questionId,
                    '\n"Message":"New answer for question:',
                    l_question,
                    '"}}'),
            utc_timestamp()
        );

    elseif l_questionId != p_questionId then

        update userSecretQuestions
            set questionid = p_questionId,
                answerHash = p_answerHash,
                lastUpdate = utc_timestamp()
        where
            userQuestionId = l_userQuestionId;

        insert activityLogs (
            userId,
            message,
            created
        ) values (
            p_userId,
            concat('{"Update":{"SequenceId":',
                    p_sequenceId,
                    '\n,"QuestionId":',
                    p_questionId,
                    '\n"Message":Updated answer for new question:',
                    l_question,
                    '"}}'),
            utc_timestamp()
        );

    elseif l_answerHash != p_answerHash then

        update userSecretQuestions
            set answerHash = p_answerHash,
                lastUpdate = utc_timestamp()
        where
            userQuestionId = l_userQuestionId;

        insert activityLogs (
            userId,
            message,
            created
        ) values (
            p_userId,
            concat('{"Update":{"SequenceId":',
                    p_sequenceId,
                    '\n,"QuestionId":',
                    p_questionId,
                    '\n"Message":Updated answer for question:',
                    l_question,
                    '"}}'),
            utc_timestamp()
        );

    end if;

    commit;

    if p_suppressFlag is not null and p_suppressFlag != 1 then
        select l_userQuestionId as userQuestionId;
    end if;

end //

delimiter ;

drop procedure if exists getSecretQuestionData;

delimiter //

--   12.   P6. getSecretQuestionData stored procedure - obtain the secret question and answer for a user that she had chosen before
create procedure getSecretQuestionData(
    in p_userId                               int ( 10 ) unsigned
)
begin

    select
        questionId,
        answerHash
    from
        userSecretQuestions
    where
        userId = p_userId
    order by
        sequenceId;

end //

delimiter ;

drop procedure if exists getUserData;

delimiter //

--   13.   P7. getUserData stored procedure - get the user data for an email address (or username) supplied
--   Called from:
--       1. login.php - to attempt to login a user
--       2. forgotPassword.php - to fetch user data for a user trying to reset her password
--       3. requestInvite.php - to check if this email exists for our user
create procedure getUserData (
    in p_userCredentials                      varchar( 128 ),
    in p_sessionKey                           varchar( 32 ),
    in p_logFlag                              tinyint ( 1 ) unsigned
)
begin

    declare l_userId                          int ( 10 ) unsigned;
    declare l_questionId1                     int ( 10 ) unsigned;
    declare l_answerHash1                     varchar( 64 );
    declare l_questionId2                     int ( 10 ) unsigned;
    declare l_answerHash2                     varchar( 64 );
    declare l_questionId3                     int ( 10 ) unsigned;
    declare l_answerHash3                     varchar( 64 );

    set l_userId = null;

    set l_questionId1 = null;
    set l_answerHash1 = null;
    set l_questionId2 = null;
    set l_answerHash2 = null;
    set l_questionId3 = null;
    set l_answerHash3 = null;

    if p_logFlag = 1 then
        insert loginAttempts (
            sessionKey,
            userCredentials,
            created
        ) values (
            p_sessionKey,
            p_userCredentials,
            utc_timestamp()
        );
    end if;

    select
        userId
    into
        l_userId
    from
        users u
    where
        p_userCredentials = email
    or
        p_userCredentials = username
    limit 1;

    if l_userId is not null then

        select
            questionId, answerHash
        into
            l_questionId1, l_answerHash1
        from
            userSecretQuestions
        where
            userId = l_userId
        and
            sequenceId = 1;

        select
            questionId, answerHash
        into
            l_questionId2, l_answerHash2
        from
            userSecretQuestions
        where
            userId = l_userId
        and
            sequenceId = 2;

        select
            questionId, answerHash
        into
            l_questionId3, l_answerHash3
        from
            userSecretQuestions
        where
            userId = l_userId
        and
            sequenceId = 3;
    end if;

    select
        userId,
        username,
        firstName,
        lastName,
        email,
        salt,
        password,
        userKey,
        accessKey,
        active,
        status,
        exclude,
        notificationMask,
        l_questionId1 as questionId1,
        l_answerHash1 as answerHash1,
        l_questionId2 as questionId2,
        l_answerHash2 as answerHash2,
        l_questionId3 as questionId3,
        l_answerHash3 as answerHash3
    from
        users
    where
        l_userId is not null
    and
        userId = l_userId;
end //

delimiter ;

-- drop table if exists appSettings;

--   14.   T7. appSettings table - all system-level settings for app
create table if not exists appSettings (
    settingId                                 int ( 10 ) unsigned not null auto_increment,        -- settingId, identity column
    name                                      varchar ( 32 ) not null,                            -- name of the setting
    value                                     varchar ( 255 ) default null,                       -- value for this setting
    enabled                                   tinyint ( 1 ) unsigned not null default 0,          -- enabled?
    created                                   datetime not null,                                  -- when was this setting created?
    lastUpdate                                datetime not null,                                  -- when was this setting last updated?
    key ( settingId ),
    unique index ix_name ( name )
) engine=innodb default character set=utf8;

-- drop table if exists appSettingsLog;

--   15.   T8. appSettingsLog table - what are all the changes we are making to appSettings table?
create table if not exists appSettingsLog (
    logId                                     int( 10 ) unsigned not null auto_increment,         -- logId, identity column
    settingId                                 int( 10 ) unsigned,                                 -- settingId (foreign key to appSettings table)
    userId                                    int( 10 ) unsigned,                                 -- user that made this change (foreign key to users table)
    value                                     varchar ( 512 ) default null,                       -- value that was updated to
    enabled                                   tinyint( 1 ) unsigned,                              -- whether this setting was updated or disabled?
    created                                   datetime not null,                                  -- when did this change occur?
    key ( logId )
) engine=innodb default character set=utf8;

drop procedure if exists getSettings;

delimiter //

--   16.   P8. getSettings stored procedure - get all the settings saved in this table
create procedure getSettings()
begin

    select
        settingId,
        name,
        value,
        enabled,
        created,
        lastUpdate
    from
        appSettings
    order by
        settingId;

end //

delimiter ;

drop procedure if exists getSetting;

delimiter //

--   17.   P9. getSetting stored procedure - get the appSettings value for the incoming name
create procedure getSetting(
    in p_name                                 varchar( 32 )
)
begin

    select
        value, enabled from appSettings
    where
        name = p_name;

end //

delimiter ;

drop procedure if exists updateSetting;

delimiter //

--   18.  P10. updateSetting stored procedure - add or update a setting name and value
create procedure updateSetting (
    in p_name                                 varchar( 32 ),
    in p_value                                varchar( 255 ),
    in p_enabled                              tinyint( 1 ) unsigned,
    in p_userId                               int ( 10 ) unsigned,
    in p_suppressFlag                         tinyint ( 1 ) unsigned
)
begin

    declare l_settingId                       int ( 10 ) unsigned;
    declare l_value                           varchar( 255 );
    declare l_enabled                         tinyint ( 1 ) unsigned;
    declare l_dataChanged                     tinyint ( 1 ) unsigned;

    set l_dataChanged = 0;
    set l_settingId = null;

    start transaction;

    select settingId, value, enabled into l_settingId, l_value, l_enabled
    from appSettings
    where name = p_name
    for update;

    if l_settingId is null then

        insert appSettings (
            name,
            value,
            enabled,
            created,
            lastUpdate
        ) values (
            p_name,
            p_value,
            p_enabled,
            utc_timestamp(),
            utc_timestamp()
        );

        select last_insert_id() into l_settingId;

        set l_dataChanged = 1;

    elseif l_value != p_value or l_enabled != p_enabled then

        update appSettings
        set value = p_value,
        enabled = p_enabled
        where settingId = l_settingId;

        set l_dataChanged = 1;

    end if;

    commit;

    if l_dataChanged = 1 then
        insert appSettingsLog (
            settingId,
            userId,
            value,
            enabled,
            created
        ) values (
            l_settingId,
            p_userId,
            p_value,
            p_enabled,
            utc_timestamp()
        );

    end if;

    if p_suppressFlag is not null and p_suppressFlag = 0 then
        select l_settingId as settingId;
    end if;

end //

delimiter ;

drop procedure if exists getEnabledSettings;

delimiter //

--   19.  P11. getEnabledSettings() stored procedure - get name/value pairs of settings that are enabled
create procedure getEnabledSettings()
begin

    select
        name,
        value
    from
        appSettings
    where
        enabled = 1
    order by
        settingId;

end //

delimiter ;

drop procedure if exists populateSettings;

delimiter //

--   20.  P12. populateSettings stored procedure - populate known settings for the application
create procedure populateSettings()
begin

    declare l_count                           int( 10 ) unsigned;

    select count(*) into l_count from appSettings;

    if l_count = 0 then
        call updateSetting('logging', '1', 1, 1, 1);
        call updateSetting('errorEmail','$$ADMIN_EMAIL_ADDRESS$$', 1, 1, 1);                     -- $$ ADMIN_EMAIL_ADDRESS $$

    end if;

end //

delimiter ;

call populateSettings();

drop procedure populateSettings;

-- drop table if exists accessLogs;

--   21.   T9. accessLogs table - store metadata for any request to our application
create table if not exists accessLogs (
    logId                                     int ( 10 ) unsigned not null auto_increment,     -- logId - identity column
    ipAddress                                 decimal ( 39, 0 ) default null,                  -- IP Address of client landing on home page
    timeZone                                  float (5, 1) default null,                       -- Timezone of client browser
    browserString                             varchar( 256 ) default null,                     -- Browser userAgent string
    referer                                   varchar( 256 ) default null,                     -- Referer from where user landed here
    created                                   datetime not null,                               -- when did this event occur?
    lastUpdate                                datetime default null,                           -- when was this access attempt last updated?
    sessionKey                                varchar( 32 ) not null,                          -- SessionKey (not PHP_SESSID)
    key ( logId ),
    index ix_sessionKey ( sessionKey )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;

drop procedure if exists logUserAccess;

delimiter //

--   22.  P13. logUserAccess stored procedure - log the access attempt made by a client/user
create procedure logUserAccess (
    in p_ipAddress                            decimal ( 39, 0 ),
    in p_browserString                        varchar( 256 ),
    in p_referer                              varchar( 256 ),
    in p_sessionKey                           varchar( 32 )
)
begin

    declare l_logId                           int( 10 );
    set l_logId = null;

    select logId into l_logId
    from accessLogs where sessionKey = p_sessionKey;

    if l_logId is null then
        insert accessLogs (
            ipAddress,
            browserString,
            referer,
            created,
            sessionKey
        ) values (
            p_ipAddress,
            p_browserString,
            p_referer,
            utc_timestamp(),
            p_sessionKey
        );

        set l_logId = last_insert_id();
    end if;

    select l_logId as logId;
end //

delimiter ;

drop procedure if exists updateTimeZone;

delimiter //

--   23.  P14. updateTimeZone stored procedure - update the timezone record for the corresponding session key
create procedure updateTimeZone(
    in p_sessionKey                           varchar( 32 ),
    in p_timezone                             float ( 5, 1 )
)
begin

    declare l_logId                           int ( 10 ) unsigned;
    set l_logId = null;

    start transaction;

    select logId into l_logId from accessLogs
    where sessionKey = p_sessionKey
    and lastUpdate is null
    for update;

    if l_logId is not null then

        update accessLogs
        set timeZone = p_timeZone,
        lastUpdate = utc_timestamp()
        where logId = l_logId;

    end if;

    commit;

    select l_logId as logId;

end //

delimiter ;

drop procedure if exists updateUser;

delimiter //

--   24.  P15. updateUser stored procedure - update user data
create procedure updateUser (
    in p_userId                               int ( 10 ) unsigned,
    in p_username                             varchar( 32 ),
    in p_firstName                            varchar( 32 ),
    in p_lastName                             varchar( 32 ),
    in p_salt                                 varchar( 32 ),
    in p_email                                varchar( 128 ),
    in p_active                               tinyint ( 1 ) unsigned,
    in p_status                               int ( 10 ) unsigned,
    in p_accessKey                            varchar( 32 ),
    in p_userKey                              varchar( 32 ),
    in p_comments                             varchar( 512 ),
    in p_createdBy                            int ( 10 ) unsigned,
    in p_notificationMask                     tinyint ( 2 ) unsigned,
    in p_suppressFlag                         tinyint ( 1 ) unsigned
)
begin

    declare l_query                           varchar( 1024 );
    declare l_message                         varchar( 4096 );
    declare l_userId                          int ( 10 ) unsigned;
    declare l_element                         varchar( 1024 );
    declare l_existingUserId                  int ( 10 ) unsigned;
    declare l_errorFlag                       tinyint ( 1 ) unsigned;
    declare l_errorMessage                    varchar( 96 );
    declare l_salt                            varchar( 32 );
    declare l_accessKey                       varchar( 32 );
    declare l_comments                        varchar( 1024 );
    declare l_nameChanged                     tinyint ( 1 ) unsigned;
    declare l_useFirstName                    varchar( 100 );
    declare l_useLastName                     varchar( 100 );
    declare l_adminName                       varchar( 201 );

    set l_salt = 'NULL';
    set l_accessKey = 'NULL';
    set l_message = '';
    set l_comments = null;
    set l_nameChanged = 0;

    if p_salt is not null then
        set l_salt = 'x';
    end if;

    if p_accessKey is not null then
        set l_accessKey = 'x';
    end if;

    set l_errorFlag = 0;
    set l_userId = p_userId;
    set l_existingUserId = null;

    start transaction;

    if p_userId > 0 then
        select firstName, lastName into l_useFirstName, l_useLastName
        from users where userId = p_userId for update;
    end if;

    if p_email is not null and p_email != '' then
        select userId into l_existingUserId
        from users where email = p_email;

        if l_existingUserId is null then
            set l_errorFlag = 0;
        elseif l_existingUserId != p_userId then
            set l_errorFlag = 1;
            set l_errorMessage = concat('Found another user with userId: ', l_existingUserId, ' that has the same email address');
        end if;
    end if;

    if l_errorFlag = 0 then
        select trim(concat(firstName, ' ', ifnull(lastName, ''))) into l_adminName from users where userId = p_createdBy;

        set l_adminName = trim(replace(l_adminName, '\'', '\'\''));

        if p_userId = 0 then

            insert users (
                username,
                firstName,
                lastName,
                firstLastName,
                lastFirstName,
                email,
                salt,
                password,
                userKey,
                accessKey,
                active,
                status,
                notificationMask,
                comments,
                created,
                lastUpdate
            ) values (
                p_username,
                p_firstName,
                p_lastName,
                trim(concat(p_firstName, ' ', ifnull(p_lastName, ''))),
                trim(concat(ifnull(p_lastName, ''), ' ', p_firstName)),
                p_email,
                p_salt,
                '',
                p_userKey,
                p_accessKey,
                p_active,
                p_status,
                p_notificationMask,
                p_comments,
                utc_timestamp(),
                utc_timestamp()
            );

            select last_insert_id() into l_userId;

            insert emailUserLog (userId, email, created) values (l_userId, p_email, utc_timestamp());

            set l_message=concat('{"NewUser":{\n',
                                    '"username":"', replace(ifnull(p_username, 'NULL'), '"', '\\"'), '",\n',
                                    '"firstName":"', replace(ifnull(p_firstName, 'NULL'), '"', '\\"'), '",\n',
                                    '"lastName":"', replace(ifnull(p_lastName, 'NULL'), '"', '\\"'), '",\n',
                                    '"email":"', ifnull(p_email, 'NULL'), '",\n',
                                    '"salt":"', l_salt, '",\n',
                                    '"accessKey":"', l_accessKey, '",\n',
                                    '"active":', ifnull(p_active, 0), ',\n',
                                    '"status":', ifnull(p_status, 0), ',\n',
                                    '"notificationMask":', ifnull(p_notificationMask, 0), ',\n');

            if p_comments is not null then
                set l_comments = replace(p_comments, '\n', '\\n');
                set l_message = concat(l_message, '"comments":"', replace(l_comments, '"', '\\"'), '",\n');
            end if;

            set l_message = concat(l_message, '"changedBy":"', replace(l_adminName, '"', '\\"'), '"}}');

        else

            set l_query = 'update users set ';
            set l_message = '{"OldUser":{\n';

            if p_firstName is not null then

                set l_query = concat(l_query, 'firstName=');

                set l_element = replace(p_firstName, '\'', '\'\'');

                set l_query = concat(l_query, '\'', l_element, '\'');
                set l_message = concat(l_message,'"firstName":"', replace(p_firstName, '"', '\\"'), '"');

                set l_nameChanged = 1;
            end if;

            if p_lastName is not null then

                if l_message != '{"OldUser":{\n' then
                    set l_query = concat(l_query, ',');
                    set l_message = concat(l_message, ',\n');
                end if;

                set l_query = concat(l_query, 'lastName=');

                if l_nameChanged = 0 then
                    set l_nameChanged = 1;
                end if;

                if p_lastName = '' then
                    set l_query = concat(l_query, 'null');
                    set l_message = concat(l_message,'"lastName":"NULL"');
                else
                    set l_element = replace(p_lastName, '\'', '\'\'');

                    set l_query = concat(l_query, '\'', l_element, '\'');
                    set l_message = concat(l_message,'"lastName":"', replace(p_lastName, '"', '\\"'), '"');

                end if;
            end if;

            if p_email is not null then

                if l_message != '{"OldUser":{\n' then
                    set l_query = concat(l_query, ',');
                    set l_message = concat(l_message, ',\n');
                end if;

                set l_query = concat(l_query, 'email=');

                if p_email = '' then
                    set l_query = concat(l_query, 'null');
                    set l_message = concat(l_message,'"email":"NULL"');
                else
                    set l_element = replace(p_email, '\'', '\'\'');

                    set l_query = concat(l_query, '\'', l_element, '\'');
                    set l_message = concat(l_message,'"email":"', replace(p_email, '"', '\\"'), '"');

                end if;
            end if;

            if p_accessKey is not null then
                if l_message != '{"OldUser":{\n' then
                    set l_query = concat(l_query, ',');
                    set l_message = concat(l_message, ',\n');
                end if;

                set l_query = concat(l_query, 'accessKey=\'', p_accessKey, '\'');
                set l_message = concat(l_message, '"accessKey":"xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"');

            end if;

            if p_active is not null then
                if l_message != '{"OldUser":{\n' then
                    set l_query = concat(l_query, ',');
                    set l_message = concat(l_message, ',\n');
                end if;

                set l_query = concat(l_query, 'active=', p_active);
                set l_message = concat(l_message, '"active":', p_active);
            end if;

            if p_status is not null then
                if l_message != '{"OldUser":{\n' then
                    set l_query = concat(l_query, ',');
                    set l_message = concat(l_message, ',\n');
                end if;

                set l_query = concat(l_query, 'status=', p_status);
                set l_message = concat(l_message, '"status":', p_status);
            end if;

            if p_notificationMask is not null then
                if l_message != '{"OldUser":{\n' then
                    set l_query = concat(l_query, ',');
                    set l_message = concat(l_message, ',\n');
                end if;

                set l_query = concat(l_query, 'notificationMask=', p_notificationMask);
                set l_message = concat(l_message, '"notificationMask":', ifnull(p_notificationMask, 0));
            end if;

            if p_comments is not null then

                if l_message != '{"OldUser":{\n' then
                    set l_query = concat(l_query, ',');
                    set l_message = concat(l_message, ',\n');
                end if;

                set l_query = concat(l_query, 'comments=');

                if p_comments = '' then
                    set l_query = concat(l_query, 'null');
                    set l_message = concat(l_message,'"comments":"NULL"');
                else
                    set l_element = replace(p_comments, '\'', '\'\'');
                    set l_comments = replace(p_comments, '\n', '\\n');

                    set l_query = concat(l_query, '\'', l_element, '\'');
                    set l_message = concat(l_message,'"comments":"', replace(l_comments, '"', '\\"'), '"');

                end if;
            end if;

            if l_nameChanged = 1 then

                if p_firstName is not null then
                    set l_useFirstName = replace(p_firstName, '\'', '\'\'');
                end if;

                if p_lastName is not null then
                    set l_useLastName = replace(p_lastName, '\'', '\'\'');
                end if;

                set l_query = concat(l_query, ', firstLastName=\'', trim(concat(l_useFirstName, ' ', ifnull(l_useLastName, ''))), '\'');
                set l_query = concat(l_query, ', lastFirstName=\'', trim(concat(ifnull(l_useLastName, ''), ' ', l_useFirstName)), '\'');
            end if;

            if l_query = 'update users set ' and l_message = '{"OldUser":{\n' then
                set l_message = '';
            elseif l_query != 'update users set ' then
                set l_query = concat(l_query, ', lastUpdate=utc_timestamp() where userId=', l_userId, ';');

                set l_message = concat(l_message, ',\n"changedBy":"', replace(l_adminName, '"', '\\"'), '" } }');

                set @statement = l_query;
                prepare stmt from @statement;
                execute stmt;
                deallocate prepare stmt;

            end if;
        end if;
    end if;

    commit;

    if l_message != '' then
        insert activityLogs (
            userId,
            message,
            created
        ) values (
            l_userId,
            l_message,
            utc_timestamp()
        );

        if l_userId != p_createdBy then
            if p_userId = 0 then
                set l_message = '{"Update":{"Message":"Created new user with userId:';
            else
                set l_message = '{"Update":{"Message":"Updated user with userId:';
            end if;

            insert activityLogs (
                userId,
                message,
                created
            ) values (
                p_createdBy,
                concat(l_message, l_userId, '"}}'),
                utc_timestamp()
            );
        end if;

    end if;

    if p_suppressFlag = 0 then
        select
            l_userId as userId,
            l_errorFlag as errorFlag,
            l_errorMessage as errorMessage;
    end if;

end //

delimiter ;

-- Drop procedure we are about to create, if it exists prior
drop procedure if exists addFirstRealUser;

delimiter //

-- First user has to be injected manually
--   25.  P16. addFirstRealUser stored procedure - add first real user for this application
create procedure addFirstRealUser ()
begin
    declare l_userId                          int ( 10 ) unsigned;
    declare l_userCount                       int ( 10 ) unsigned;
    set l_userId = null;
    set l_userCount = 0;

    select count(*) into l_userCount from users;

    if l_userCount = 1 then
        -- Insert first user that can log in
        call updateUser (
            0,
            '$$ADMIN_USERNAME$$',                     -- $$ ADMIN_USERNAME $$
            '$$ADMIN_FIRST_NAME$$',                   -- $$ ADMIN_FIRST_NAME $$
            '$$ADMIN_LAST_NAME$$',                    -- $$ ADMIN_LAST_NAME $$
            '$$ADMIN_SALT$$',                         -- $$ ADMIN_SALT $$
            '$$ADMIN_EMAIL_ADDRESS$$',                -- $$ ADMIN_EMAIL_ADDRESS $$
            1,
            3,
            null,
            '$$ADMIN_USER_KEY$$',                     -- $$ ADMIN_USER_KEY $$
            'First real user added by SQL script',
            1,
            1,
            1
        );
    end if;
end //

delimiter ;

-- Invoke SP to insert first real user
call addFirstRealUser();

drop procedure addFirstRealUser;

drop procedure if exists populateSecretQuestionDataForFirstRealUser;

delimiter //

--   26.  P17. addFirstRealUser stored procedure - add first real user for this application
create procedure populateSecretQuestionDataForFirstRealUser()
begin

    declare l_count                           int ( 10 ) unsigned;
    declare l_userId                          int ( 10 ) unsigned;
    declare l_question                        varchar( 128 );

    select count(*) into l_count from userSecretQuestions;

    if l_count = 0 then

        select userId into l_userId from users where email = '$$ADMIN_EMAIL_ADDRESS$$';                -- $$ ADMIN_EMAIL_ADDRESS $$

        call updateSecretQuestion(l_userId, $$ADMIN_QUESTION_ID1$$, 1, '$$ADMIN_ANSWER_HASH1$$', 1);   -- $$ ADMIN_QUESTION_ID1 $$, $$ ADMIN_ANSWER_HASH1 $$
        call updateSecretQuestion(l_userId, $$ADMIN_QUESTION_ID2$$, 1, '$$ADMIN_ANSWER_HASH2$$', 1);   -- $$ ADMIN_QUESTION_ID2 $$, $$ ADMIN_ANSWER_HASH2 $$
        call updateSecretQuestion(l_userId, $$ADMIN_QUESTION_ID3$$, 1, '$$ADMIN_ANSWER_HASH3$$', 1);   -- $$ ADMIN_QUESTION_ID3 $$, $$ ADMIN_ANSWER_HASH3 $$

    end if;
end //

delimiter ;

call populateSecretQuestionDataForFirstRealUser();

drop procedure populateSecretQuestionDataForFirstRealUser;

-- drop table if exists sessionData;

--   27.  T10. sessionData table - to store session data in cases where Apache sessions fail us
create table sessionData (
    id                                        int ( 10 ) unsigned not null auto_increment,
    sessionId                                 varchar( 32 ) not null,
    sessionKey                                varchar( 32 ) not null,
    value                                     varchar( 256 ) default null,
    intValue                                  int ( 10 ) default null,
    created                                   datetime not null,
    lastUpdate                                datetime not null,
    key id ( id ),
    unique index ix_sessionId_sessionKey ( sessionId, sessionKey )
) engine=innodb default character set=utf8;

drop procedure if exists getSession;

delimiter //

--   28.  P18. getSession stored procedure - to fetch data for a session variable previously stored
create procedure getSession(
   in p_sessionId                            varchar( 32 ),
   in p_sessionKey                           varchar( 32 ),
   in p_deleteFlag                           tinyint ( 1 ) unsigned
)
begin

   declare l_id                              int ( 10 ) unsigned;
   declare l_value                           varchar( 255 );
   declare l_intValue                        int ( 10 );

   set l_id = null;

   select
       id,
       value,
       intValue
   into
       l_id,
       l_value,
       l_intValue
   from
       sessionData
   where
       sessionId = p_sessionId
       and sessionKey = p_sessionKey;

   if l_id is not null and p_deleteFlag = 1 then

       delete from sessionData
       where id = l_id;
   end if;

   select
       l_id as id,
       l_value as value,
       l_intValue as intValue;

end //

delimiter ;

drop procedure if exists setSession;

delimiter //

--   29.  P19. setSession stored procedure - to save data into the sessionData table for a name-value pair
create procedure setSession(
   in p_sessionId                            varchar( 32 ),
   in p_sessionKey                           varchar( 32 ),
   in p_value                                varchar( 256 ),
   in p_intValue                             int ( 10 )
)
begin

   declare l_id                              int ( 10 ) unsigned;
   set l_id = null;

   select id into l_id
   from sessionData
   where sessionId = p_sessionId
   and sessionKey = p_sessionKey;

   if l_id is null then

       insert sessionData(
           sessionId,
           sessionKey,
           value,
           intValue,
           created,
           lastUpdate
       ) values (
           p_sessionId,
           p_sessionKey,
           p_value,
           p_intValue,
           utc_timestamp(),
           utc_timestamp()
       );

       select last_insert_id() into l_id;

   else

     update sessionData
     set value = p_value,
     intValue = p_intValue,
     lastUpdate = utc_timestamp()
     where
     id = l_id;

   end if;

   select l_id as id;

end //

delimiter ;

-- drop table if exists mailApiKeys;

--   30.  T11. mailApiKeys table to store API keys that anyone can employ to send email
create table if not exists mailApiKeys (
    apiId                                     int ( 10 ) unsigned              not null auto_increment,
    apiKey                                    varchar( 32 )                    not null,
    email                                     varchar( 128 )                   not null,
    active                                    tinyint ( 1 ) unsigned           not null default 0,
    created                                   datetime                         not null,
    lastUpdate                                datetime                         not null,
    key ( apiId ),
    unique index i_apiKey ( apiKey )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;

drop procedure if exists populateApiKey;

delimiter //

--   31.  P20. populateApiKey stored procedure to add the first API key we will employ to call the web service
create procedure populateApiKey()
begin

    declare l_apiCount                        int ( 10 ) unsigned;
    set l_apiCount = 0;

    select count(*) into l_apiCount
    from mailApiKeys;

    if l_apiCount = 0 then

        insert mailApiKeys (
            apiKey,
            email,
            active,
            created,
            lastUpdate
        ) values (
            '$$API_KEY$$',           -- $$ API_KEY $$
            '$$ADMIN_EMAIL_ADDRESS$$',            -- $$ ADMIN_EMAIL_ADDRESS $$
            1,
            utc_timestamp(),
            utc_timestamp()
        );

    end if;

end //

delimiter ;

call populateApiKey();

drop procedure populateApiKey;

drop procedure if exists checkMailApiKey;

delimiter //

--   32.  P21. checkMailApiKey stored procedure to check if the furnished API key has a valid active flag
create procedure checkMailApiKey(
    in p_apiKey                               varchar( 32 )
)
begin

    select
        apiId as apiKeyId,
        active,
        email
    from
        mailApiKeys
    where
        apiKey = p_apiKey;

end //

delimiter ;

-- drop table if exists mails;

--   33.  T12. mails table to store emails that need to be dispatched
create table if not exists mails (
    mailId                                    int ( 10 ) unsigned              not null auto_increment,
    sender                                    varchar( 64 )                    default null,
    senderEmail                               varchar( 128 )                   default null,
    recipients                                varchar( 4096 )                  not null,
    ccRecipients                              varchar( 4096 )                  default null,
    bccRecipients                             varchar( 4096 )                  default null,
    replyTo                                   varchar( 128 )                   default null,
    subject                                   varchar( 236 )                   not null,
    subjectPrefix                             varchar( 16 )                    default null,
    body                                      text                             default null,
    ready                                     tinyint ( 1 ) unsigned           not null default 0,
    hasAttachments                            tinyint ( 1 ) unsigned           not null default 0,
    importance                                tinyint ( 1 ) unsigned           not null default 0,
    timestamp                                 datetime                         default null,
    created                                   datetime                         not null,
    key ( mailId )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;

-- drop table if exists mailAttachments;

--   34.  T13. mailAttachments table to store attachment data for certain emails
create table if not exists mailAttachments (
    mailAttachmentId                         int ( 10 ) unsigned              not null auto_increment,
    mailId                                   int ( 10 ) unsigned              not null,
    filename                                 varchar( 1024 )                  not null,
    filesize                                 int ( 10 ) unsigned              not null,
    attachment                               longblob                         not null,
    created                                  datetime                         not null,
    key ( mailAttachmentId )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;

-- drop table if exists mailsLog;

--   35.  T14. mailsLog table to log all the emails we successfully dispatch via scheduling
create table if not exists mailsLog (
    logId                                     int ( 10 ) unsigned              not null auto_increment,
    apiKeyId                                  int ( 10 ) unsigned              not null,
    mailId                                    int ( 10 ) unsigned              default null,
    sender                                    varchar( 128 )                   not null,
    recipient                                 varchar( 4096 )                  not null,
    subject                                   varchar( 255 )                   not null,
    size                                      int ( 10 ) unsigned              default null,
    timestamp                                 datetime                         not null,
    key ( logId )
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8;

drop procedure if exists addEmail;

delimiter //

--   36.  P22. addEmail stored procedure to add new email to mails table, mail would not be dispatched unless all attachments are in too
create procedure addEmail (
    in p_apiKey                              varchar( 32 ),
    in p_sender                              varchar( 64 ),
    in p_senderEmail                         varchar( 128 ),
    in p_recipients                          varchar( 4096 ),
    in p_ccRecipients                        varchar( 4096 ),
    in p_bccRecipients                       varchar( 4096 ),
    in p_replyTo                             varchar( 128 ),
    in p_subject                             varchar( 236 ),
    in p_subjectPrefix                       varchar( 64 ),
    in p_body                                text,
    in p_markMailAsReady                     tinyint ( 1 ) unsigned,
    in p_hasAttachments                      tinyint ( 1 ) unsigned,
    in p_importance                          tinyint ( 1 ) unsigned,
    in p_timestamp                           datetime
)
begin

    declare l_apiId                          int ( 10 ) unsigned;
    set l_apiId = null;

    select apiId into l_apiId
    from mailApiKeys
    where apiKey = p_apiKey
    and active = 1;

    if l_apiId is not null then

       insert mails (
           sender,
            senderEmail,
            recipients,
            ccRecipients,
            bccRecipients,
            replyTo,
            subject,
            subjectPrefix,
            body,
            ready,
            hasAttachments,
            importance,
            timestamp,
            created
        ) values (
            p_sender,
            p_senderEmail,
            p_recipients,
            p_ccRecipients,
            p_bccRecipients,
            p_replyTo,
            p_subject,
            p_subjectPrefix,
            p_body,
            p_markMailAsReady,
            p_hasAttachments,
            p_importance,
            p_timestamp,
            utc_timestamp()
        );

        select last_insert_id() as mailId;

    end if;
end //

delimiter ;

drop procedure if exists addMailAttachment;

delimiter //

--   37.  P23. addMailAttachment stored procedure to add one attachment to a previously saved email message
create procedure addMailAttachment (
    in p_mailId                              int ( 10 ) unsigned,
    in p_filename                            varchar ( 1024 ),
    in p_filesize                            int ( 10 ) unsigned,
    in p_attachment                          longblob
)
begin

    declare l_mailId             int ( 10 ) unsigned;
    declare l_hasAttachments     bit;

    set l_mailId = null;
    set l_hasAttachments = null;

    select
        hasAttachments, mailId
    into
        l_hasAttachments, l_mailId
    from
        mails
    where
        mailId = p_mailId;

    if l_hasAttachments is not null and l_hasAttachments = 0 then
        update
            mails
        set
            hasAttachments = 1
        where
            mailId = p_mailId;
    end if;

    if l_mailId is not null then
        insert mailAttachments (
            mailId,
            filename,
            filesize,
            attachment,
            created
        ) values (
            p_mailId,
            p_filename,
            p_filesize,
            p_attachment,
            utc_timestamp()
        );

        select last_insert_id() as mailAttachmentId;
    else
        select null as mailAttachmentId;
    end if;
end //

delimiter ;

drop procedure if exists markEmailAsReady;

delimiter //

--   38.  P24. markEmailAsReady stored procedure to mark email as ready to send
create procedure markEmailAsReady (
    in p_mailId                              int ( 10 ) unsigned
)
begin

    declare l_ready bit;
    set l_ready = null;

    select
        ready into l_ready
    from
        mails
    where
        mailId = p_mailId;

    if l_ready is not null and l_ready = 0 then
        update
            mails
        set
            ready = 1
        where
            mailId = p_mailId;

    end if;

    select p_mailId as mailId;
end //

delimiter ;

drop procedure if exists getEmailToSend;

delimiter //

--   39.  P25. getEmailToSend stored procedure to get the next email to dispatch, hasAttachments would tell you if you need to call getAttachmentsForEmail
create procedure getEmailToSend (
    in p_timestamp                            datetime
)
begin

    select
        mailId,
        sender,
        senderEmail,
        recipients,
        subject,
        subjectPrefix,
        ccRecipients,
        bccRecipients,
        replyTo,
        body,
        hasAttachments,
        importance,
        created
    from
        mails
    where
            ready = 1
        and
            ((timestamp is null) or (timestamp < p_timestamp))
    order by
        mailId
    limit 1;
end //

delimiter ;

drop procedure if exists getAttachmentsForEmail;

delimiter //

--   40.  P26. getAttachmentsForEmail stored procedure to get attachments that are defined or were added for this email, assuming ready is still set to false for this mailId
create procedure getAttachmentsForEmail (
    in p_mailId                              int ( 10 ) unsigned
)
begin

    select
        mailAttachmentId,
        mailId,
        filename,
        filesize,
        attachment,
        created
    from
        mailAttachments
    where
        mailId = p_mailId
    order by
        mailAttachmentId;
end //

delimiter ;

drop procedure if exists deleteEmail;

delimiter //

--   41.  P27. getAttachmentsForEmail stored procedure to delete this email, we have successfully dispatched it into the ether
create procedure deleteEmail (
    in p_mailId                               int ( 10 ) unsigned
)
begin

    declare l_mailId                          int ( 10 ) unsigned;

    set l_mailId = null;

    select mailId
        into l_mailId
    from
        mails
    where
        mailId = p_mailId;

    if l_mailId is not null then

        -- This is moot, but still delete attachments prior to deleting emails
        delete
        from
            mailAttachments
        where
            mailId = p_mailId;

        delete
        from
            mails
        where
            mailId = p_mailId;

        select p_mailId as mailId;
    end if;
end //

delimiter ;

drop procedure if exists logEmailDispatch;

delimiter //

--   42.  P28. logEmailDispatch stored procedure to log the use case where we have successfully sent a mail
create procedure logEmailDispatch (
    in p_apiKeyId                             int ( 10 ) unsigned,
    in p_senderEmail                          varchar( 128 ),
    in p_recipients                           varchar( 4096 ),
    in p_subject                              varchar( 255 ),
    in p_size                                 int ( 10 ) unsigned
)
begin

    -- Log this email that we dispatched
    insert mailsLog (
        apiKeyId,
        sender,
        recipient,
        subject,
        size,
        timestamp
    ) values (
        p_apiKeyId,
        p_senderEmail,
        p_recipients,
        p_subject,
        p_size,
        utc_timestamp()
    );

    select last_insert_id() as logId;

end //

delimiter ;

-- drop table if exists userLoginDetails;

--   43.  T15. userLoginDetails table stores details about users that login, auxillary details necessary for 2FA, and logout functionality
create table if not exists userLoginDetails (
    loginId                                   int ( 10 ) unsigned not null auto_increment,
    userId                                    int ( 10 ) unsigned not null,
    cookie                                    varchar( 32 ) default null,                       -- Unique, cannot be changed
    sessionId                                 varchar( 32 ) not null,
    browserHash                               varchar( 32 ) default null,
    active                                    tinyint ( 1 ) unsigned not null default 0,
    created                                   datetime not null,
    lastChecked                               datetime not null,
    expires                                   datetime not null,
    key ( loginId ),
    index ix_userId_cookie ( userId, cookie )
) engine=innodb default character set=utf8;
