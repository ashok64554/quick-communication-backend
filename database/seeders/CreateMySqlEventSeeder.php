<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DB;

class CreateMySqlEventSeeder extends Seeder
{
    public function run()
    {
        /*
        ****************
        MySql EVENTS
        ****************
        */

        /* 1. update-report */
        /* we have created a manual function for this
        DB::statement("DROP EVENT IF EXISTS `update-report`");

        $server = env('DB_USERNAME', 'root').'@`localhost`';
        DB::statement("CREATE DEFINER=$server EVENT `update-report` ON SCHEDULE EVERY 1 MINUTE STARTS '2022-08-25 23:45:00' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE `send_sms` SET `total_delivered` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` = 'DELIVRD') + (SELECT COUNT(*) FROM `send_sms_histories` WHERE `send_sms`.`id` = `send_sms_histories`.`send_sms_id` AND `stat` = 'DELIVRD'), 
            `total_failed` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` NOT IN ('Pending','Accepted','Invalid','BLACK','DELIVRD')) + (SELECT COUNT(*) FROM `send_sms_histories` WHERE `send_sms`.`id` = `send_sms_histories`.`send_sms_id` AND `stat` NOT IN ('Pending','Accepted','Invalid','BLACK','DELIVRD')),
            `total_block_number` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` = 'BLACK') + (SELECT COUNT(*) FROM `send_sms_histories` WHERE `send_sms`.`id` = `send_sms_histories`.`send_sms_id` AND `stat` = 'BLACK'),
            `total_invalid_number` = (SELECT COUNT(*) FROM `send_sms_queues` WHERE `send_sms`.`id` = `send_sms_queues`.`send_sms_id` AND `stat` = 'Invalid') + (SELECT COUNT(*) FROM `send_sms_histories` WHERE `send_sms`.`id` = `send_sms_histories`.`send_sms_id` AND `stat` = 'Invalid'),
            `status` = CASE WHEN `total_contacts` <= (`total_block_number` + `total_invalid_number` + `total_delivered` + `total_failed`) THEN 'Completed' ELSE `status` END
            WHERE `status` = 'Ready-to-complete'");

        */


        /* 2. daily-submission-logs */
        DB::statement("DROP EVENT IF EXISTS `daily-submission-logs`");

        $server = env('DB_USERNAME', 'root').'@`localhost`';
        DB::statement("CREATE DEFINER=$server EVENT `daily-submission-logs` ON SCHEDULE EVERY 1 DAY STARTS '2023-05-25 03:00:00' ON COMPLETION NOT PRESERVE ENABLE DO 
            CALL `migrateDailyLogs`()");

        /* 3. accepted-to-deliver-status */
        DB::statement("DROP EVENT IF EXISTS `accepted-to-deliver-status`");

        $server = env('DB_USERNAME', 'root').'@`localhost`';
        DB::statement("CREATE DEFINER=$server EVENT `accepted-to-deliver-status` ON SCHEDULE EVERY 1 DAY STARTS '2023-05-25 00:30:00' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE `send_sms_queues` SET `stat` = 'DELIVRD' WHERE stat='ACCEPTED' AND err='000' AND done_date IS NOT NULL;");

        /* 4. accepted-to-failed-status */
        DB::statement("DROP EVENT IF EXISTS `accepted-to-failed-status`");

        $server = env('DB_USERNAME', 'root').'@`localhost`';
        DB::statement("CREATE DEFINER=$server EVENT `accepted-to-failed-status` ON SCHEDULE EVERY 1 DAY STARTS '2023-05-25 00:45:00' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE `send_sms_queues` SET `stat` = 'FAILED'  WHERE stat='ACCEPTED' AND err NOT IN ('000', 'XX1') AND done_date IS NOT NULL;");

        /*This Event not proper right now */
        /* 5. reUpdatePendingEvent */
        /*
        DB::statement("DROP EVENT IF EXISTS `reUpdatePendingEvent`");

        $server = env('DB_USERNAME', 'root').'@`localhost`';
        DB::statement("CREATE DEFINER=$server EVENT `reUpdatePendingEvent` ON SCHEDULE EVERY 1 DAY STARTS '2023-05-25 02:00:00' ON COMPLETION NOT PRESERVE ENABLE DO 
            CALL `reUpdatePending`()");
        */

        // scheduler enabled
        DB::statement("SET GLOBAL event_scheduler = ON");

        /*
        ****************
        MySql PROCEDURES
        ****************
        */
        // procedure 1
        $procedure = "DROP PROCEDURE IF EXISTS `migrateDailyLogs`;
        CREATE PROCEDURE `migrateDailyLogs`()
        BEGIN
          DECLARE done INT DEFAULT FALSE;
          DECLARE route_id INT;
          DECLARE migrate_daily_logs CURSOR FOR 
          
          # modify the select statement to returns IDs, which will be assigned the variable `route_id`

          SELECT id FROM primary_routes WHERE deleted_at IS NULL;
          DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=TRUE;

          OPEN migrate_daily_logs;

          read_loop: LOOP
            FETCH migrate_daily_logs INTO route_id;

            IF done THEN
              LEAVE read_loop;
            END IF;

            # modify the insert statement to perform your operation with the `route_id` 
            INSERT `daily_submission_logs` SET 
            `submission_date` = DATE_SUB(CURDATE(),INTERVAL 1 DAY),
            `sms_gateway` = route_id,
            `submission` = (SELECT  SUM(submission) submission
                FROM
                ( 
                    SELECT COUNT(*) submission FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND primary_route_id = route_id
                    UNION ALL
                    SELECT COUNT(*) submission FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND primary_route_id = route_id
                ) val1),

            `submission_credit_used` = (SELECT  SUM(submission_credit_used) submission_credit_used
                FROM
                ( 
                    SELECT SUM(use_credit) submission_credit_used FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND primary_route_id = route_id
                    UNION ALL
                    SELECT SUM(use_credit) submission_credit_used FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND primary_route_id = route_id
                ) val2),

            `auto_submission` = (SELECT  SUM(auto_submission) auto_submission
                FROM
                ( 
                    SELECT COUNT(*) auto_submission FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `is_auto` != 0 AND primary_route_id = route_id
                    UNION ALL
                    SELECT COUNT(*) auto_submission FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `is_auto` != 0 AND primary_route_id = route_id
                ) val3),

            `auto_submission_credit` = (SELECT  SUM(auto_submission_credit) auto_submission_credit
                FROM
                ( 
                    SELECT SUM(use_credit) auto_submission_credit FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `is_auto` != 0 AND primary_route_id = route_id
                    UNION ALL
                    SELECT SUM(use_credit) auto_submission_credit FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `is_auto` != 0 AND primary_route_id = route_id
                ) val4),

            `overall_delivered` = (SELECT  SUM(auto_submission) auto_submission
                FROM
                ( 
                    SELECT COUNT(*) auto_submission FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat`= 'DELIVRD' AND primary_route_id = route_id
                    UNION ALL
                    SELECT COUNT(*) auto_submission FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat`= 'DELIVRD' AND primary_route_id = route_id
                ) val5),

            `overall_delivered_credit` = (SELECT  SUM(overall_delivered_credit) overall_delivered_credit
                FROM
                ( 
                    SELECT SUM(use_credit) overall_delivered_credit FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat`= 'DELIVRD' AND primary_route_id = route_id
                    UNION ALL
                    SELECT SUM(use_credit) overall_delivered_credit FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat`= 'DELIVRD' AND primary_route_id = route_id
                ) val6),

            `actual_delivered` = (SELECT  SUM(actual_delivered) actual_delivered
                FROM
                ( 
                    SELECT COUNT(*) actual_delivered FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` = 'DELIVRD' AND `is_auto` = 0 AND primary_route_id = route_id
                    UNION ALL
                    SELECT COUNT(*) actual_delivered FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` = 'DELIVRD' AND `is_auto` = 0 AND primary_route_id = route_id
                ) val7),

            `actual_delivered_credit` = (SELECT  SUM(actual_delivered_credit) actual_delivered_credit
                FROM
                ( 
                    SELECT SUM(use_credit) actual_delivered_credit FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` = 'DELIVRD' AND `is_auto` = 0 AND primary_route_id = route_id
                    UNION ALL
                    SELECT SUM(use_credit) actual_delivered_credit FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` = 'DELIVRD' AND `is_auto` = 0 AND primary_route_id = route_id
                ) val8),

            `other_than_delivered` = (SELECT  SUM(actual_delivered_credit) actual_delivered_credit
                FROM
                ( 
                    SELECT COUNT(*) actual_delivered_credit FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` != 'DELIVRD' AND primary_route_id = route_id
                    UNION ALL
                    SELECT COUNT(*) actual_delivered_credit FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` != 'DELIVRD' AND primary_route_id = route_id
                ) val9),

            `other_than_delivered_credit` = (SELECT  SUM(actual_delivered_credit) actual_delivered_credit
                FROM
                ( 
                    SELECT SUM(use_credit) actual_delivered_credit FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` != 'DELIVRD' AND primary_route_id = route_id
                    UNION ALL
                    SELECT SUM(use_credit) actual_delivered_credit FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` != 'DELIVRD' AND primary_route_id = route_id
                ) val10),
            `created_at` = now(),
            `updated_at` = now();

            SET done=FALSE;
          END LOOP;

          CLOSE migrate_daily_logs;
        END;";
  
        \DB::unprepared($procedure);

        // procedure 2
        $procedure2 = "DROP PROCEDURE IF EXISTS `getTotalCountBySenderID`;
            CREATE PROCEDURE `getTotalCountBySenderID` (
                IN `senderId` VARCHAR(6), 
                IN `from_date` DATE, 
                IN `to_date` DATE
            )
        BEGIN 

            -- clear/initialize session variables 
            SET 
                @tableSendSmsQueuesCount = 0, 
                @tableSendSmsHistoriesCount = 0; 
            
            START TRANSACTION; 

            -- get record count from send_sms_queues
            SELECT COUNT(*) INTO @tableSendSmsQueuesCount 
            FROM send_sms_queues 
            INNER JOIN `send_sms` 
                ON `send_sms_queues`.`send_sms_id` = `send_sms`.`id` 
            WHERE sender_id COLLATE utf8mb4_unicode_ci = senderId COLLATE utf8mb4_unicode_ci
              AND DATE(`send_sms_queues`.`created_at`) >= from_date 
              AND DATE(`send_sms_queues`.`created_at`) <= to_date;

            -- get record count from send_sms_histories
            SELECT COUNT(*) INTO @tableSendSmsHistoriesCount 
            FROM send_sms_histories 
            INNER JOIN `send_sms` 
                ON `send_sms_histories`.`send_sms_id` = `send_sms`.`id` 
            WHERE sender_id COLLATE utf8mb4_unicode_ci = senderId COLLATE utf8mb4_unicode_ci
              AND DATE(`send_sms_histories`.`created_at`) >= from_date 
              AND DATE(`send_sms_histories`.`created_at`) <= to_date; 

            -- return the total count
            SELECT @tableSendSmsQueuesCount + @tableSendSmsHistoriesCount AS total_count; 
            
            COMMIT; 

        END;";

        \DB::unprepared($procedure2);

        // procedure 3
        $procedure3 = "DROP PROCEDURE IF EXISTS `getDeliveredCount`;
            CREATE PROCEDURE `getDeliveredCount` (
                IN `sendSmsId` BIGINT(11)
            )
        BEGIN 

        -- clear/initialize session variables 
        SET 
            @tableSendSmsQueuesCount = 0, 
            @tableSendSmsHistoriesCount = 0; 

        START TRANSACTION;

        -- get record count from the source tables 
        SELECT COUNT(*) INTO @tableSendSmsQueuesCount FROM send_sms_queues WHERE stat = 'DELIVRD' AND send_sms_id = sendSmsId; 

        -- get record count from the source tables 
        SELECT COUNT(*) INTO @tableSendSmsHistoriesCount FROM send_sms_histories WHERE stat = 'DELIVRD' AND send_sms_id = sendSmsId;
         
        -- return the sum of the two table sizes 
        SELECT @tableSendSmsQueuesCount + @tableSendSmsHistoriesCount as total_delivered; 
        COMMIT; 
        END";

        \DB::unprepared($procedure3);


        // procedure 4
        $procedure4 = "DROP PROCEDURE IF EXISTS `getDateWiseConsumption`;
            CREATE PROCEDURE `getDateWiseConsumption` (
                IN `campaign_send_date` DATE, 
                IN `userId` BIGINT(11)
            )
        BEGIN 

        START TRANSACTION; 

        -- get record count from the source tables 
        SELECT date(`send_sms`.`campaign_send_date_time`) as campaign_send_date, 
        COUNT(send_sms_histories.id) as total_submission_history, 
        SUM(send_sms_histories.use_credit) as total_credit_submission_history, 
        COUNT(IF(send_sms_histories.stat = 'DELIVRD', 0, NULL)) as delivered_history_count,
        COUNT(IF(send_sms_histories.stat = 'Invalid', 0, NULL)) as invalid_history_count,
        COUNT(IF(send_sms_histories.stat = 'BLACK', 0, NULL)) as black_history_count,
        COUNT(IF(send_sms_histories.stat = 'EXPIRED', 0, NULL)) as expired_history_count,
        COUNT(IF(send_sms_histories.stat IN ('FAILED', 'UNDELIV'), 0, NULL)) as failed_history_count,
        COUNT(IF(send_sms_histories.stat = 'REJECTD', 0, NULL)) as rejected_history_count,
        COUNT(IF(send_sms_histories.stat NOT IN ('DELIVRD','Invalid','BLACK','EXPIRED','FAILED','UNDELIV','REJECTD'), 0, NULL)) as process_history_count
        FROM send_sms
        INNER JOIN `send_sms_histories` on `send_sms_histories`.`send_sms_id` = `send_sms`.`id` WHERE date(`send_sms`.`campaign_send_date_time`) = campaign_send_date AND `send_sms`.`user_id` = userId

        UNION ALL

        SELECT date(`send_sms`.`campaign_send_date_time`) as campaign_send_date, 
        COUNT(send_sms_queues.id) as total_submission_history, 
        SUM(send_sms_queues.use_credit) as total_credit_submission_history, 
        COUNT(IF(send_sms_queues.stat = 'DELIVRD', 0, NULL)) as delivered_history_count,
        COUNT(IF(send_sms_queues.stat = 'Invalid', 0, NULL)) as invalid_history_count,
        COUNT(IF(send_sms_queues.stat = 'BLACK', 0, NULL)) as black_history_count,
        COUNT(IF(send_sms_queues.stat = 'EXPIRED', 0, NULL)) as expired_history_count,
        COUNT(IF(send_sms_queues.stat IN ('FAILED', 'UNDELIV'), 0, NULL)) as failed_history_count,
        COUNT(IF(send_sms_queues.stat = 'REJECTD', 0, NULL)) as rejected_history_count,
        COUNT(IF(send_sms_queues.stat NOT IN ('DELIVRD','Invalid','BLACK','EXPIRED','FAILED','REJECTD'), 0, NULL)) as process_history_count
        FROM send_sms
        INNER JOIN `send_sms_queues` on `send_sms_queues`.`send_sms_id` = `send_sms`.`id` WHERE date(`send_sms`.`campaign_send_date_time`) = campaign_send_date AND `send_sms`.`user_id` = userId;
        COMMIT; 
        END";
        \DB::unprepared($procedure4);


        //This procedure not working properly right now
        // procedure 5
        /*
        $procedure5 = "DROP PROCEDURE IF EXISTS `reUpdatePending`;
            
        CREATE PROCEDURE reUpdatePending()
        BEGIN
          DECLARE start_id INT DEFAULT 1;
          DECLARE chunk_size INT DEFAULT 10000;
          DECLARE total_records INT;
          
          -- Get the total number of records
          SELECT COUNT(*) INTO total_records FROM send_sms_queues WHERE `is_auto`= 1 AND `stat` = 'Pending';

          WHILE start_id <= total_records DO
            -- Update records in chunks
            UPDATE `send_sms_queues` SET `response_token`= COALESCE(response_token, LPAD(FLOOR(RAND() * 9999999999999999999), 19, '0')), 
            `submit_date`= COALESCE(submit_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 4) second)), 
            `done_date`= COALESCE(done_date, DATE_ADD(created_at, INTERVAL FLOOR((RAND() * 5) + 13) second)),
            `stat` = 'DELIVRD',
            `err` = '000',
            `sub` = '001',
            `dlvrd` = '001',
            `status` = 'Completed'
            WHERE `is_auto`= 1 AND `stat` = 'Pending' AND id BETWEEN start_id AND (start_id + chunk_size - 1);

            -- Increment start_id for the next chunk
            SET start_id = start_id + chunk_size;
          END WHILE;
        END";
        \DB::unprepared($procedure5);
        */

        // procedure 6
        $procedure6 = "DROP PROCEDURE IF EXISTS `getVoiceAnsweredCount`;
            CREATE PROCEDURE `getVoiceAnsweredCount` (
                IN `voiceSmsId` BIGINT(11)
            )
        BEGIN 

        -- clear/initialize session variables 
        SET 
            @tablevoiceSmsQueuesCount = 0, 
            @tablevoiceSmsHistoriesCount = 0; 

        START TRANSACTION;

        -- get record count from the source tables 
        SELECT COUNT(*) INTO @tablevoiceSmsQueuesCount FROM voice_sms_queues WHERE stat = 'Answered' AND voice_sms_id = voiceSmsId; 

        -- get record count from the source tables 
        SELECT COUNT(*) INTO @tablevoiceSmsHistoriesCount FROM voice_sms_histories WHERE stat = 'Answered' AND voice_sms_id = voiceSmsId;
         
        -- return the sum of the two table sizes 
        SELECT @tablevoiceSmsQueuesCount + @tablevoiceSmsHistoriesCount as total_answered; 
        COMMIT; 
        END";

        \DB::unprepared($procedure6);


        //2. PROCEDURE
        //1.
        /*
        DELIMITER $$
        DROP PROCEDURE IF EXISTS getDeliveredCount $$
        CREATE PROCEDURE getDeliveredCount(IN `sendSmsId` BIGINT(11))
        BEGIN 
        -- clear/initialize session variables 
        SET @tableSendSmsQueuesCount = 0, @tableSendSmsHistoriesCount = 0; 
        START TRANSACTION; 
        -- get record count from the source tables 
        SELECT COUNT(*) INTO @tableSendSmsQueuesCount FROM send_sms_queues WHERE stat = 'DELIVRD' AND send_sms_id = sendSmsId; 
        SELECT COUNT(*) INTO @tableSendSmsHistoriesCount FROM send_sms_histories WHERE stat = 'DELIVRD' AND send_sms_id = sendSmsId; 
        -- return the sum of the two table sizes 
        SELECT @tableSendSmsQueuesCount + @tableSendSmsHistoriesCount as total_delivered; 
        COMMIT; 
        END
        */


        //2.
        /*
        DELIMITER $$
        DROP PROCEDURE IF EXISTS getTotalCountBySenderID $$
        CREATE PROCEDURE getTotalCountBySenderID(IN `senderId` VARCHAR(6), IN `from_date` DATE, IN `to_date` DATE)
        BEGIN 
        -- clear/initialize session variables 
        SET @tableSendSmsQueuesCount = 0, @tableSendSmsHistoriesCount = 0; 
        START TRANSACTION; 
        -- get record count from the source tables 
        SELECT COUNT(*) INTO @tableSendSmsQueuesCount FROM send_sms_queues INNER JOIN `send_sms` ON `send_sms_queues`.`send_sms_id` = `send_sms`.`id` WHERE sender_id = senderId AND DATE(`send_sms_queues`.`created_at`) >= from_date AND DATE(`send_sms_queues`.`created_at`) <= to_date; 
        SELECT COUNT(*) INTO @tableSendSmsHistoriesCount FROM send_sms_histories INNER JOIN `send_sms` ON `send_sms_histories`.`send_sms_id` = `send_sms`.`id` WHERE sender_id = senderId AND DATE(`send_sms_histories`.`created_at`) >= from_date AND DATE(`send_sms_histories`.`created_at`) <= to_date; 
        -- return the sum of the two table sizes 
        SELECT @tableSendSmsQueuesCount + @tableSendSmsHistoriesCount as total_count; 
        COMMIT; 
        END
        */

        
        //3.
        /*
        DELIMITER $$
        CREATE DEFINER=`root`@`localhost` PROCEDURE `migrateDailyLogs`()
        BEGIN
          DECLARE done INT DEFAULT FALSE;
          DECLARE route_id INT;
          DECLARE migrate_daily_logs CURSOR FOR 
          
          # modify the select statement to returns IDs, which will be assigned the variable `_post_id`
          # the following statement gets all wp attachments that are missing attachment metadata
          SELECT id FROM primary_routes WHERE deleted_at IS NULL;
          DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=TRUE;

          OPEN migrate_daily_logs;

          read_loop: LOOP
            FETCH migrate_daily_logs INTO route_id;

            IF done THEN
              LEAVE read_loop;
            END IF;

            # modify the insert statement to perform your operation with the `id` 
            INSERT `daily_submission_logs` SET 
                    `submission_date` = DATE_SUB(CURDATE(),INTERVAL 1 DAY),
                    `sms_gateway` = route_id,
                    `submission` = (SELECT  SUM(submission) submission
                        FROM
                        ( 
                            SELECT COUNT(*) submission FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND primary_route_id = route_id
                            UNION ALL
                            SELECT COUNT(*) submission FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND primary_route_id = route_id
                        ) val1),

                    `submission_credit_used` = (SELECT  SUM(submission_credit_used) submission_credit_used
                        FROM
                        ( 
                            SELECT SUM(use_credit) submission_credit_used FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND primary_route_id = route_id
                            UNION ALL
                            SELECT SUM(use_credit) submission_credit_used FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND primary_route_id = route_id
                        ) val2),

                    `auto_submission` = (SELECT  SUM(auto_submission) auto_submission
                        FROM
                        ( 
                            SELECT COUNT(*) auto_submission FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `is_auto` != 0 AND primary_route_id = route_id
                            UNION ALL
                            SELECT COUNT(*) auto_submission FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `is_auto` != 0 AND primary_route_id = route_id
                        ) val3),

                    `auto_submission_credit` = (SELECT  SUM(auto_submission_credit) auto_submission_credit
                        FROM
                        ( 
                            SELECT SUM(use_credit) auto_submission_credit FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `is_auto` != 0 AND primary_route_id = route_id
                            UNION ALL
                            SELECT SUM(use_credit) auto_submission_credit FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `is_auto` != 0 AND primary_route_id = route_id
                        ) val4),

                    `overall_delivered` = (SELECT  SUM(auto_submission) auto_submission
                        FROM
                        ( 
                            SELECT COUNT(*) auto_submission FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat`= 'DELIVRD' AND primary_route_id = route_id
                            UNION ALL
                            SELECT COUNT(*) auto_submission FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat`= 'DELIVRD' AND primary_route_id = route_id
                        ) val5),

                    `overall_delivered_credit` = (SELECT  SUM(overall_delivered_credit) overall_delivered_credit
                        FROM
                        ( 
                            SELECT SUM(use_credit) overall_delivered_credit FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat`= 'DELIVRD' AND primary_route_id = route_id
                            UNION ALL
                            SELECT SUM(use_credit) overall_delivered_credit FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat`= 'DELIVRD' AND primary_route_id = route_id
                        ) val6),

                    `actual_delivered` = (SELECT  SUM(actual_delivered) actual_delivered
                        FROM
                        ( 
                            SELECT COUNT(*) actual_delivered FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` = 'DELIVRD' AND `is_auto` = 0 AND primary_route_id = route_id
                            UNION ALL
                            SELECT COUNT(*) actual_delivered FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` = 'DELIVRD' AND `is_auto` = 0 AND primary_route_id = route_id
                        ) val7),

                    `actual_delivered_credit` = (SELECT  SUM(actual_delivered_credit) actual_delivered_credit
                        FROM
                        ( 
                            SELECT SUM(use_credit) actual_delivered_credit FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` = 'DELIVRD' AND `is_auto` = 0 AND primary_route_id = route_id
                            UNION ALL
                            SELECT SUM(use_credit) actual_delivered_credit FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` = 'DELIVRD' AND `is_auto` = 0 AND primary_route_id = route_id
                        ) val8),

                    `other_than_delivered` = (SELECT  SUM(actual_delivered_credit) actual_delivered_credit
                        FROM
                        ( 
                            SELECT COUNT(*) actual_delivered_credit FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` != 'DELIVRD' AND primary_route_id = route_id
                            UNION ALL
                            SELECT COUNT(*) actual_delivered_credit FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` != 'DELIVRD' AND primary_route_id = route_id
                        ) val9),

                    `other_than_delivered_credit` = (SELECT  SUM(actual_delivered_credit) actual_delivered_credit
                        FROM
                        ( 
                            SELECT SUM(use_credit) actual_delivered_credit FROM `send_sms_queues` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` != 'DELIVRD' AND primary_route_id = route_id
                            UNION ALL
                            SELECT SUM(use_credit) actual_delivered_credit FROM `send_sms_histories` WHERE date(`created_at`) = DATE_SUB(CURDATE(),INTERVAL 1 DAY) AND `stat` != 'DELIVRD' AND primary_route_id = route_id
                        ) val10),
                    `created_at` = now(),
                    `updated_at` = now();

            SET done=FALSE;
          END LOOP;

          CLOSE migrate_daily_logs;
        END$$
        DELIMITER ;
        */
    }
}
