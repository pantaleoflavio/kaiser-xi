export type LeagueRole = 'commissioner' | 'co_commissioner' | 'participant' | string;

export type LeagueReference = {
  key: string;
  label: string;
};

export type League = {
  id: number;
  name: string;
  description: string | null;
  max_participants: number | null;
  season: {
    id: number;
    name: string;
    competition: {
      id: number;
      name: string;
    };
  };
  type: LeagueReference;
  status: LeagueReference;
  my_role: LeagueRole | null;
};

export type LeagueCollectionResponse = {
  data: League[];
};