# Implementation Plan

- [x] 1. Set up test infrastructure and fixtures






  - [x] 1.1 Create drug factory states for each drug form

    - Add factory states: tablet, capsule, syrup, suspension, injection, patch, cream, drops, inhaler, combination_pack, suppository, sachet, lozenge, pessary, enema, iv_bag, nebulizer
    - Each state should set appropriate form, unit, and bottle_size
    - _Requirements: 1.9, 2.5, 3.4, 4.5_


  - [x] 1.2 Create test data constants for frequency multipliers





    - Define FREQUENCY_MULTIPLIERS constant with OD=1, BD=2, TDS=3, QDS=4, Q6H=4, Q8H=3, Q12H=2
    - _Requirements: 1.2-1.8_

- [x] 2. Write property tests for piece-based drugs






  - [x] 2.1 Write property test for piece-based quantity calculation

    - **Property 1: Piece-based quantity calculation**
    - Generate random piece-based drugs, doses (1-10), frequencies, durations (1-30 days)
    - Verify quantity = dose × frequency_multiplier × duration
    - **Validates: Requirements 1.1, 1.9**

  - [x] 2.2 Write property test for frequency multiplier consistency


    - **Property 2: Frequency multiplier consistency**
    - Test all frequency codes return correct multipliers
    - **Validates: Requirements 1.2-1.8**

- [x] 3. Write property tests for volume-based drugs






  - [x] 3.1 Write property test for volume-based bottle calculation

    - **Property 3: Volume-based bottle calculation**
    - Generate random syrups/suspensions with various bottle sizes
    - Verify bottles = ceil(ml × freq × days / bottle_size)
    - **Validates: Requirements 2.1, 2.2, 2.5**

- [x] 4. Write property tests for interval-based drugs






  - [x] 4.1 Write property test for patch quantity calculation

    - **Property 4: Interval-based patch calculation**
    - Generate random change intervals (1-7 days) and durations
    - Verify quantity = ceil(duration / interval)
    - **Validates: Requirements 3.1, 3.4**

- [x] 5. Write property tests for fixed-unit drugs






  - [x] 5.1 Write property test for fixed-unit defaults

    - **Property 5: Fixed-unit drug defaults**
    - Test creams, drops, inhalers, combination packs default to 1
    - **Validates: Requirements 4.1-4.5**

  - [x] 5.2 Write property test for context-aware drops

    - **Property 6: Context-aware drops interpretation**
    - Test "2 QDS x 7 days" with drops drug returns 1 bottle
    - **Validates: Requirements 4.8**


- [x] 6. Write property tests for special patterns





  - [x] 6.1 Write property test for split dose calculation


    - **Property 7: Split dose quantity calculation**
    - Generate random A-B-C patterns and durations
    - Verify quantity = (A + B + C) × duration
    - **Validates: Requirements 5.1**

  - [x] 6.2 Write property test for STAT dose


    - **Property 8: STAT dose quantity**
    - Generate random STAT doses
    - Verify quantity = dose (no duration multiplication)
    - **Validates: Requirements 6.1, 6.2**

  - [x] 6.3 Write property test for PRN with max daily


    - **Property 9: PRN with max daily calculation**
    - Generate random max daily and durations
    - Verify quantity = max_daily × duration
    - **Validates: Requirements 7.1**

  - [x] 6.4 Write property test for taper patterns


    - **Property 10: Taper pattern quantity calculation**
    - Generate random taper patterns [D1, D2, ..., Dn]
    - Verify quantity = sum of all doses
    - **Validates: Requirements 8.1**

  - [x] 6.5 Write property test for custom intervals


    - **Property 11: Custom interval quantity calculation**
    - Generate random dose per interval and number of intervals
    - Verify quantity = dose × intervals
    - **Validates: Requirements 9.1**

  - [x] 6.6 Write property test for custom interval storage


    - **Property 12: Custom interval pattern storage**
    - Verify schedule_pattern contains interval hours
    - **Validates: Requirements 9.3**

- [x] 7. Checkpoint - Ensure all property tests pass





  - Ensure all tests pass, ask the user if questions arise.

- [-] 8. Run Playwright MCP browser tests for UI verification




  - [x] 8.1 Test tablet prescription via Playwright MCP

    - Use `mcp_playwright_browser_navigate` to go to consultation page with active patient
    - Use `mcp_playwright_browser_snapshot` to capture page state
    - Use `mcp_playwright_browser_click` to click on Prescriptions tab
    - Use `mcp_playwright_browser_click` to enable Smart mode toggle
    - Use `mcp_playwright_browser_click` to open drug selector and search for tablet drug (e.g., Amlodipine)
    - Use `mcp_playwright_browser_type` to enter "1 OD x 30 days" in smart input field
    - Use `mcp_playwright_browser_snapshot` to verify interpretation panel shows quantity: 30
    - Use `mcp_playwright_browser_click` to submit prescription
    - Use `mcp_playwright_browser_snapshot` to verify prescription appears in list with correct quantity
    - _Requirements: 10.1, 10.2, 10.3_

  - [x] 8.2 Test syrup prescription via Playwright MCP







    - Use `mcp_playwright_browser_navigate` to consultation page
    - Use `mcp_playwright_browser_click` to select syrup drug (e.g., Paracetamol Syrup)
    - Use `mcp_playwright_browser_type` to enter "5ml TDS x 7 days" in smart input
    - Use `mcp_playwright_browser_snapshot` to verify interpretation panel shows correct bottle count
    - Use `mcp_playwright_browser_click` to submit and verify prescription list
    - _Requirements: 10.1, 10.2, 10.3_

  - [x] 8.3 Test drops prescription via Playwright MCP





    - Use `mcp_playwright_browser_click` to select drops drug (e.g., Chloramphenicol Eye Drops)
    - Use `mcp_playwright_browser_type` to enter "2 QDS x 7 days" in smart input
    - Use `mcp_playwright_browser_snapshot` to verify interpretation panel shows quantity: 1 bottle
    - Use `mcp_playwright_browser_click` to submit and verify prescription list
    - _Requirements: 10.1, 10.2, 10.3, 10.4_

  - [x] 8.4 Test split dose pattern via Playwright MCP





    - Use `mcp_playwright_browser_click` to select tablet drug (e.g., Prednisolone 5mg)
    - Use `mcp_playwright_browser_type` to enter "1-0-1 x 30 days" in smart input
    - Use `mcp_playwright_browser_snapshot` to verify interpretation panel shows quantity: 60
    - Use `mcp_playwright_browser_click` to submit and verify prescription list
    - _Requirements: 10.1, 10.2, 10.3_

  - [x] 8.5 Test STAT dose via Playwright MCP

















    - Use `mcp_playwright_browser_click` to select injection drug (e.g., Hydrocortisone)
    - Use `mcp_playwright_browser_type` to enter "1 STAT" in smart input
    - Use `mcp_playwright_browser_snapshot` to verify interpretation panel shows quantity: 1
    - Use `mcp_playwright_browser_click` to submit and verify prescription list
    - _Requirements: 10.1, 10.2, 10.3_

  - [x] 8.6 Test taper pattern via Playwright MCP





    - Use `mcp_playwright_browser_click` to select tablet drug (e.g., Prednisolone 5mg)
    - Use `mcp_playwright_browser_type` to enter "4-3-2-1 taper" in smart input
    - Use `mcp_playwright_browser_snapshot` to verify interpretation panel shows quantity: 10
    - Use `mcp_playwright_browser_click` to submit and verify prescription list
    - _Requirements: 10.1, 10.2, 10.3_

  - [x] 8.7 Test custom interval (antimalarial) via Playwright MCP





    - Use `mcp_playwright_browser_click` to select antimalarial drug
    - Use `mcp_playwright_browser_type` to enter "4 tabs 0h,8h,24h,36h,48h,60h" in smart input
    - Use `mcp_playwright_browser_snapshot` to verify interpretation panel shows quantity: 24
    - Use `mcp_playwright_browser_click` to submit and verify prescription list
    - _Requirements: 10.1, 10.2, 10.3_

  - [x] 8.8 Test capsule prescription via Playwright MCP





    - Use `mcp_playwright_browser_click` to select capsule drug (e.g., Amoxicillin 500mg)
    - Use `mcp_playwright_browser_type` to enter "1 TDS x 7 days" in smart input
    - Use `mcp_playwright_browser_snapshot` to verify interpretation panel shows quantity: 21
    - Use `mcp_playwright_browser_click` to submit and verify prescription list
    - _Requirements: 10.1, 10.2, 10.3_

  - [x] 8.9 Test injection prescription via Playwright MCP















    - Use `mcp_playwright_browser_click` to select injection drug (e.g., Ceftriaxone 1g)
    - Use `mcp_playwright_browser_type` to enter "1 OD x 5 days" in smart input
    - Use `mcp_playwright_browser_snapshot` to verify interpretation panel shows quantity: 5
    - Use `mcp_playwright_browser_click` to submit and verify prescription list
    - _Requirements: 10.1, 10.2, 10.3_

- [x] 9. Run Playwright MCP tests for edge cases






  - [x] 9.1 Test invalid input handling via Playwright MCP

    - Use `mcp_playwright_browser_type` to enter invalid input patterns
    - Use `mcp_playwright_browser_snapshot` to verify error messages are displayed
    - Verify form prevents submission (submit button disabled or shows error)
    - _Requirements: 10.5_


  - [x] 9.2 Test case insensitivity via Playwright MCP

    - Use `mcp_playwright_browser_type` to enter "2 bd x 5 DAYS" (mixed case)
    - Use `mcp_playwright_browser_snapshot` to verify parsing works correctly
    - _Requirements: 10.4_


  - [x] 9.3 Test whitespace handling via Playwright MCP

    - Use `mcp_playwright_browser_type` to enter "  2   BD   x   5   days  " (extra whitespace)
    - Use `mcp_playwright_browser_snapshot` to verify parsing works correctly
    - _Requirements: 10.4_

- [ ] 10. Final Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
