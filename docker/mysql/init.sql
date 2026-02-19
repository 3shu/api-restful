-- MySQL initialization script
-- Database for Users microservice

CREATE DATABASE IF NOT EXISTS users_db;

USE users_db;

-- Users table will be created by Doctrine migrations
-- This file is ready for any additional initialization needed

SELECT 'MySQL Users Database Initialized' AS Status;
