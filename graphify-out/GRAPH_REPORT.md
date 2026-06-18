# Graph Report - .  (2026-06-05)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 74 nodes · 63 edges · 16 communities (5 shown, 11 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 8|Community 8]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 12|Community 12]]
- [[_COMMUNITY_Community 13|Community 13]]
- [[_COMMUNITY_Community 14|Community 14]]

## God Nodes (most connected - your core abstractions)
1. `Patient Booking Page` - 5 edges
2. `send_json()` - 4 edges
3. `require_login()` - 4 edges
4. `Admin Doctors Page` - 4 edges
5. `require_role()` - 3 edges
6. `Admin Appointments Page` - 3 edges
7. `Admin Dashboard Page` - 3 edges
8. `Admin Schedules Page` - 3 edges
9. `is_logged_in()` - 2 edges
10. `check_login_rate_limit()` - 2 edges

## Surprising Connections (you probably didn't know these)
- None detected - all connections are within the same source files.

## Import Cycles
- None detected.

## Communities (16 total, 11 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.17
Nodes (3): Admin Doctors Page, Patient Booking Page, Patient Family Page

### Community 1 - "Community 1"
Cohesion: 0.31
Nodes (5): check_login_rate_limit(), is_logged_in(), require_login(), require_role(), send_json()

## Knowledge Gaps
- **3 isolated node(s):** `Login Form`, `Registration Page`, `Staff Schedule Page`
  These have ≤1 connection - possible missing edges or undocumented components.
- **11 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What connects `Login Form`, `Registration Page`, `Staff Schedule Page` to the rest of the system?**
  _3 weakly-connected nodes found - possible documentation gaps or missing edges._