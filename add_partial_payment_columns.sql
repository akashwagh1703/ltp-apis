-- Add partial payment columns to bookings table
-- Run this SQL in your database

ALTER TABLE bookings 
ADD COLUMN paid_amount DECIMAL(10,2) DEFAULT 0.00 AFTER final_amount,
ADD COLUMN pending_amount DECIMAL(10,2) DEFAULT 0.00 AFTER paid_amount,
ADD COLUMN advance_percentage DECIMAL(5,2) NULL AFTER pending_amount;

-- Update existing bookings to set paid_amount based on payment_status
UPDATE bookings 
SET paid_amount = final_amount, 
    pending_amount = 0 
WHERE payment_status = 'success';

UPDATE bookings 
SET paid_amount = 0, 
    pending_amount = final_amount 
WHERE payment_status = 'pending';
