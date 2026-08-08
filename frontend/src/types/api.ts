export type ResourceResponse<T> = {
  data: T;
};

export type CollectionResponse<T> = {
  data: T[];
};

export type PaginationLinks = {
  first: string;
  last: string;
  prev: string | null;
  next: string | null;
};

export type PaginationMetaLink = {
  url: string | null;
  label: string;
  active: boolean;
};

export type PaginationMeta = {
  current_page: number;
  from: number | null;
  last_page: number;
  links: PaginationMetaLink[];
  path: string;
  per_page: number;
  to: number | null;
  total: number;
};

export type PaginatedResponse<T> = CollectionResponse<T> & {
  links: PaginationLinks;
  meta: PaginationMeta;
};