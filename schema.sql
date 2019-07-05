SET SQL_SAFE_UPDATES = 0;
ALTER TABLE oc_circles_circles ADD unique_short_id VARCHAR(14);
ALTER TABLE oc_circles_circles ADD index(unique_short_id);
UPDATE oc_circles_circles SET unique_short_id = substr(unique_id, 1, 14);

CREATE TRIGGER tr_oc_circles_circles_insert_unique_short_id
    BEFORE INSERT
    ON oc_circles_circles FOR EACH ROW
    SET NEW.unique_short_id = SUBSTRING(NEW.unique_id, 1, 14);

CREATE TRIGGER tr_oc_circles_circles_update_unique_short_id
    BEFORE UPDATE
    ON oc_circles_circles FOR EACH ROW
    SET NEW.unique_short_id = SUBSTRING(NEW.unique_id, 1, 14);