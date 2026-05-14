# TODO

- [ ] Explore current deletion logic for branches and related DB constraints.
- [ ] Update branch deletion to allow deletion when users exist (or when related records exist), instead of throwing: "Cannot delete branch because it has related users.".
- [ ] Ensure UI and tests support the new deletion behavior (possibly use cascade or set FK to NULL strategy).
- [x] Update/extend tests in tests/BranchTest.php accordingly.
- [ ] Run phpunit (or equivalent) to verify (may require PHPUnit install/zip extension). 


