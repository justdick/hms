---
description: Work on a feature spec - create new specs or implement existing spec tasks (Kiro-style spec-driven development)
---

# Spec-Driven Development Workflow

This workflow replicates Kiro's spec-driven development experience. Use this to create new feature specs or implement tasks from existing specs.

## Spec Location

All specs are stored in `.kiro/specs/[feature-name]/` with three files:
- `requirements.md` - User stories and acceptance criteria
- `design.md` - Technical design and data models
- `tasks.md` - Implementation plan with checkboxes

## Context Files

Always read the steering documents for context:
- `.kiro/steering/hms-architecture.md` - Core architecture patterns
- `.kiro/steering/laravel-boost.md` - Laravel conventions
- For insurance work: `.kiro/steering/hms-insurance.md`
- For frontend work: `.kiro/steering/hms-frontend.md`
- For testing work: `.kiro/steering/hms-testing.md`

---

## Mode 1: Create a New Spec

When the user wants to create a new feature spec:

1. **Understand the Feature**
   - Ask clarifying questions about the feature requirements
   - Identify affected modules and stakeholders

2. **Create the Spec Directory**
   ```
   .kiro/specs/[feature-name]/
   ```

3. **Create `requirements.md`**
   - Start with Introduction and Glossary sections
   - Write user stories in format: "As a [role], I want [goal], so that [benefit]"
   - Write acceptance criteria in WHEN/THEN format:
     ```
     WHEN [condition] THEN the System SHALL [behavior]
     ```
   - Reference requirements with numbers (e.g., _Requirements: 1.3, 1.4_)

4. **Create `design.md`**
   - Document technical approach
   - Define database schema changes
   - List new/modified controllers, services, models
   - Define API endpoints
   - Document frontend components needed

5. **Create `tasks.md`**
   - Organize into phases (Database, Backend, Frontend, Polish)
   - Use checkbox format: `- [ ] Task description`
   - Sub-tasks use indentation: `  - [ ] Sub-task`
   - Reference requirements: `_Requirements: X.X_`
   - Include checkpoints: "Ensure all tests pass"

---

## Mode 2: List Available Specs

When user asks to see available specs:

// turbo
1. List all directories in `.kiro/specs/`
2. For each spec, read the first few lines of `requirements.md` to get the summary
3. Present as a numbered list with brief descriptions

---

## Mode 3: Start/Continue a Task

When the user wants to work on a spec:

1. **Load the Spec**
   - Read all three files: `requirements.md`, `design.md`, `tasks.md`
   - Identify the next unchecked task(s)

2. **Load Context**
   - Read relevant steering documents based on the task type
   - Check existing similar implementations in the codebase

3. **Implement the Task**
   - Follow the task description exactly
   - Reference the design document for technical details
   - Ensure implementation satisfies the linked requirements
   - Write tests as specified

4. **Update Progress**
   - Mark completed tasks with `[x]` in `tasks.md`
   - If a checkpoint is reached, run tests: `php artisan test`

5. **Report Progress**
   - Summarize what was completed
   - Show next tasks in the queue
   - Note any blockers or questions

---

## Mode 4: Check Spec Progress

When user asks about progress on a spec:

1. Read the `tasks.md` file
2. Count completed `[x]` vs pending `[ ]` tasks
3. Calculate percentage complete
4. Show current phase and next tasks

---

## Quick Commands

- `/spec list` - Show all available specs
- `/spec new [name]` - Create a new spec
- `/spec [name]` - Start/continue working on a spec
- `/spec [name] status` - Check progress on a spec

---

## Task Naming Convention

Tasks should be named clearly with action verbs:
- "Create migration for X"
- "Add endpoint to Y controller"
- "Create Z component"
- "Write property tests for X"
- "Update navigation to include Y"

## Best Practices

1. **Atomic Tasks** - Each task should be completable in one sitting
2. **Test Coverage** - Include test tasks after feature tasks
3. **Checkpoints** - Add "Ensure all tests pass" between phases
4. **Traceability** - Always link tasks to requirements
5. **Progressive Disclosure** - Start with backend, then frontend
