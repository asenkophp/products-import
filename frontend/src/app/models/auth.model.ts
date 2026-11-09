export interface AuthUser {
  id: number;
  username: string;
}

export interface LoginResponse {
  token: string;
  /** ISO-дата окончания действия токена. */
  expiresAt: string;
  user: AuthUser;
}
