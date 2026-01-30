# HomeSync Issues and Fixes

## Critical Issues Found

### 1. Session Management
- [ ] No auto-logout after inactivity (10 min timeout required)
- [ ] No logout button in landlord dashboard

### 2. Hardcoded Data & Configuration
- [ ] SmsService.php has placeholder API credentials
- [ ] gate/index2.php has hardcoded database credentials
- [ ] Multiple db_config.php files with potential inconsistencies

### 3. Security Issues
- [ ] Gate access is token-only, no password authentication
- [ ] Permanent gate links with no expiration
- [ ] No session timeout for security

### 4. Database Inconsistencies
- [ ] Mixed use of house_number vs unit_number across files
- [ ] Multiple db_config files (main, gate/, gate/api/)
- [ ] Schema inconsistencies in visitor logging

### 5. Functionality Issues
- [ ] SMS not working due to placeholder credentials
- [ ] Gate system not properly integrated
- [ ] No error handling for failed database connections

## Implementation Plan

### Phase 1: Session Management & Security
- [ ] Add session timeout (10 min inactivity)
- [ ] Add logout button to sidebar.php
- [ ] Implement session activity tracking

### Phase 2: Configuration Cleanup
- [ ] Move SMS credentials to environment/config
- [ ] Remove hardcoded DB credentials from gate/index2.php
- [ ] Standardize db_config.php usage across all files

### Phase 3: Gate Security Enhancement
- [ ] Add password authentication for gate personnel
- [ ] Implement secure login system for gate access
- [ ] Add gate personnel management in landlord dashboard

### Phase 4: Database Consistency
- [ ] Fix house_number vs unit_number inconsistencies
- [ ] Standardize database connection usage
- [ ] Update visitor logging to use consistent field names

### Phase 5: Error Handling & Reliability
- [ ] Add proper error handling for database failures
- [ ] Implement graceful degradation for SMS failures
- [ ] Add logging for debugging issues
