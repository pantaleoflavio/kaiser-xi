import { useEffect, useMemo, useState } from 'react';

const PER_PAGE = 10;
export function useEligiblePlayerFilters() {
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [role, setRoleValue] = useState('');
  const [clubId, setClubIdValue] = useState('');
  const [page, setPage] = useState(1);
  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setSearch(searchInput.trim());
      setPage(1);
    }, 350);
    return () => window.clearTimeout(timeout);
  }, [searchInput]);
  const filters = useMemo(
    () => ({ search, role, club_id: clubId ? Number(clubId) : 0, page, per_page: PER_PAGE }),
    [clubId, page, role, search],
  );
  const setRole = (value: string) => {
    setRoleValue(value);
    setPage(1);
  };
  const setClubId = (value: string) => {
    setClubIdValue(value);
    setPage(1);
  };
  return { searchInput, setSearchInput, role, setRole, clubId, setClubId, page, setPage, filters };
}