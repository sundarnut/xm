import os;

''' Update the following to actual values '''

dbServerName = "";                                        ''' Machine name or IP address of MySQL DB Server '''
dbName = "";                                              ''' Database name on this server '''
dbUsername = "";                                          ''' User name on this database '''
dbPassword = "";                                          ''' Password for this user '''

adminUsername = "";                                       ''' username for primary admin '''
firstName = "";                                           ''' First name '''
lastName = "";                                            ''' Last Name '''
adminEmailAddress = "";                                   ''' Email address of admin '''
adminSalt = "";                                           ''' Admin Salt '''
adminUserKey = "";                                        ''' Admin Key '''

fqUri = "";                                               ''' Fully qualified URI to app '''
cookieQualifier = "";                                     ''' Website associated with this cookie '''

adminQuestionId1 = "";                                    ''' Admin Question ID 1 '''                                   
adminAnswerHash1 = "";                                    ''' Admin Answer Hash 1 '''
adminQuestionId2 = "";                                    ''' Admin Question ID 2 '''
adminAnswerHash2 = "";                                    ''' Admin Answer Hash 2 '''
adminQuestionId3 = "";                                    ''' Admin Question ID 3 '''
adminAnswerHash3 = "";                                    ''' Admin Answer Hash 3 '''

with open("functions.php", "wt") as fout:
    with open("functions.original.php", "rt") as fin:
        for line in fin:

            nextLine = line;

            if ("$$DATABASE_SERVER$$" in line)                  : nextLine = nextLine.replace("$$DATABASE_SERVER$$", dbServerName);
            if ("$$DATABASE_NAME$$" in line)                    : nextLine = nextLine.replace("$$DATABASE_NAME$$", dbName);
            if ("$$DB_USERNAME$$" in line)                      : nextLine = nextLine.replace("$$DB_USERNAME$$", dbUsername);
            if ("$$DB_PASSWORD$$" in line)                      : nextLine = nextLine.replace("$$DB_PASSWORD$$", dbPassword);
            if ("$$FULL_URL$$" in line)                         : nextLine = nextLine.replace("$$FULL_URL$$", fqUri);
            if ("$$SITE_COOKIE_QUALIFIER$$" in line)            : nextLine = nextLine.replace("$$SITE_COOKIE_QUALIFIER$$", cookieQualifier);

            fout.write(nextLine);

with open("xm.sql", "wt") as fout:
    with open("xm.original.sql", "rt") as fin:
        for line in fin:

            nextLine = line;

            if ("$$DATABASE_NAME$$" in line)                    : nextLine = nextLine.replace("$$DATABASE_NAME$$", dbName);
            if ("$$ADMIN_USERNAME$$" in line)                   : nextLine = nextLine.replace("$$ADMIN_USERNAME$$", adminUsername);
            if ("$$ADMIN_FIRST_NAME$$" in line)                 : nextLine = nextLine.replace("$$ADMIN_FIRST_NAME$$", firstName);
            if ("$$ADMIN_LAST_NAME$$" in line)                  : nextLine = nextLine.replace("$$ADMIN_LAST_NAME$$", lastName);
            if ("$$ADMIN_SALT$$" in line)                       : nextLine = nextLine.replace("$$ADMIN_SALT$$", adminSalt);
            if ("$$ADMIN_EMAIL_ADDRESS$$" in line)              : nextLine = nextLine.replace("$$ADMIN_EMAIL_ADDRESS$$", adminEmailAddress);
            if ("$$ADMIN_USER_KEY$$" in line)                   : nextLine = nextLine.replace("$$ADMIN_USER_KEY$$", adminUserKey);

            if ("$$ADMIN_QUESTION_ID1$$" in line)               : nextLine = nextLine.replace("$$ADMIN_QUESTION_ID1$$", adminQuestionId1);
            if ("$$ADMIN_ANSWER_HASH1$$" in line)               : nextLine = nextLine.replace("$$ADMIN_ANSWER_HASH1$$", adminAnswerHash1);
            if ("$$ADMIN_QUESTION_ID2$$" in line)               : nextLine = nextLine.replace("$$ADMIN_QUESTION_ID2$$", adminQuestionId2);
            if ("$$ADMIN_ANSWER_HASH2$$" in line)               : nextLine = nextLine.replace("$$ADMIN_ANSWER_HASH2$$", adminAnswerHash2);
            if ("$$ADMIN_QUESTION_ID3$$" in line)               : nextLine = nextLine.replace("$$ADMIN_QUESTION_ID3$$", adminQuestionId3);
            if ("$$ADMIN_ANSWER_HASH3$$" in line)               : nextLine = nextLine.replace("$$ADMIN_ANSWER_HASH3$$", adminAnswerHash3);

            ''' Comment out the next line only if you know what you're doing! '''
            ''' if ("-- drop database" in line)                     : nextLine = nextLine.replace("-- drop database", "drop database"); '''

            fout.write(nextLine);

os.chdir("_static");

with open("scripts.js", "wt") as fout:
    with open("scripts.original.js", "rt") as fin:
        for line in fin:

            nextLine = line;

            if ("$$SITE_URL$$" in line)                         : nextLine = nextLine.replace("$$SITE_URL$$", fqUri);

            fout.write(nextLine);

os.chdir("..");

print("Complete!");
