DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `getAdminDateWiseConsumption`(IN `campaign_send_date` DATE, IN `userId` JSON)
    READS SQL DATA
BEGIN 
        START TRANSACTION; 
        -- get record count from the source tables 
        SELECT `send_sms`.`campaign_send_date_time`, SUM(`send_sms`.`total_contacts`) as total_contacts, SUM(`send_sms`.`total_block_number`) as total_block_number, SUM(`send_sms`.`total_invalid_number`) as total_invalid_number, SUM(`send_sms`.`total_credit_deduct`) as total_credit_deduct, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date) as `total_queues_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date) as `total_history_count`, (SELECT count(*) from `send_sms_queues` where date( `send_sms_queues`.`created_at`) = campaign_send_date AND `stat` = "DELIVRD") as `delivered_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` = "DELIVRD") as `delivered_history_count`, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date AND `stat` = "Invalid") as `invalid_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` = "Invalid") as `invalid_history_count`, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date AND `stat` = "BLACK") as `blacklist_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` = "BLACK") as `blacklist_history_count`, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date AND `stat` = "EXPIRED") as `expired_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` = "EXPIRED") as `expired_history_count`, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date AND `stat` = "FAILED") as `failed_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` = "FAILED") as `failed_history_count`, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date AND `stat` in ("REJECTD", "REJECT")) as `rejected_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` in ("REJECTD", "REJECT")) as `rejected_history_count`, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date AND `stat` not in ("DELIVRD","Invalid","BLACK","EXPIRED","FAILED","REJECTD","REJECT")) as `other_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` not in ("DELIVRD","Invalid","BLACK","EXPIRED","FAILED","REJECTD","REJECT")) as `other_history_count` from `send_sms` where date(`send_sms`.`campaign_send_date_time`) = campaign_send_date AND `send_sms`.`user_id` in (userId);
        COMMIT; 
        END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `getChildDateWiseConsumption`(IN `campaign_send_date` DATE, IN `userId` JSON, IN `parentId` INT)
BEGIN 
        START TRANSACTION; 
        -- get record count from the source tables 
        SELECT `send_sms`.`campaign_send_date_time`, SUM(`send_sms`.`total_contacts`) as total_contacts, SUM(`send_sms`.`total_block_number`) as total_block_number, SUM(`send_sms`.`total_invalid_number`) as total_invalid_number, SUM(`send_sms`.`total_credit_deduct`) as total_credit_deduct, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date) as `total_queues_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date) as `total_history_count`, (SELECT count(*) from `send_sms_queues` where date( `send_sms_queues`.`created_at`) = campaign_send_date AND `stat` = "DELIVRD") as `delivered_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` = "DELIVRD") as `delivered_history_count`, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date AND `stat` = "Invalid") as `invalid_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` = "Invalid") as `invalid_history_count`, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date AND `stat` = "BLACK") as `blacklist_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` = "BLACK") as `blacklist_history_count`, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date AND `stat` = "EXPIRED") as `expired_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` = "EXPIRED") as `expired_history_count`, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date AND `stat` = "FAILED") as `failed_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` = "FAILED") as `failed_history_count`, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date AND `stat` in ("REJECTD", "REJECT")) as `rejected_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` in ("REJECTD", "REJECT")) as `rejected_history_count`, (SELECT count(*) from `send_sms_queues` where date(`send_sms_queues`.`created_at`) = campaign_send_date AND `stat` not in ("DELIVRD","Invalid","BLACK","EXPIRED","FAILED","REJECTD","REJECT")) as `other_queue_count`, (SELECT count(*) from `send_sms_histories` where date(`send_sms_histories`.`created_at`) = campaign_send_date AND `stat` not in ("DELIVRD","Invalid","BLACK","EXPIRED","FAILED","REJECTD","REJECT")) as `other_history_count` from `send_sms` where date(`send_sms`.`campaign_send_date_time`) = campaign_send_date AND `send_sms`.`user_id` in (userId) AND `send_sms`.`parent_id` = parentId;
        COMMIT; 
        END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `getDeliveredCount`(IN `sendSmsId` BIGINT(11))
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
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `getTotalCountBySenderID`(IN `senderId` VARCHAR(6), IN `from_date` DATE, IN `to_date` DATE)
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
        END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `migrateDailyLogs`(IN idx int)
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
