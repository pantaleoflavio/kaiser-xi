import { AccountProfileForm } from '../components/account/AccountProfileForm';
import { AccountSummary } from '../components/account/AccountSummary';
import { ChangePasswordForm } from '../components/account/ChangePasswordForm';;
import { useAuth } from '../auth/useAuth';

export function AccountPage() {
  const { user } = useAuth();

  if (!user) return null;

  return (
    <div className="mx-auto max-w-3xl space-y-8">
      <AccountSummary user={user} />
      <AccountProfileForm />
      <ChangePasswordForm />
    </div>
  );
}