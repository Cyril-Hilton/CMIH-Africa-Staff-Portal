<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PowerBiReportLink extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['active'=>'boolean', 'revision'=>'integer']; }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public static function validUrl(string $url): bool {
        if (! filter_var($url, FILTER_VALIDATE_URL)) return false;
        $parts = parse_url($url);
        return ($parts['scheme'] ?? '') === 'https'
            && strtolower($parts['host'] ?? '') === 'app.powerbi.com'
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && (! isset($parts['port']) || $parts['port'] === 443)
            && preg_match('#^/(groups/|links/|reportEmbed(?:$|/))#i', rawurldecode($parts['path'] ?? '/')) === 1;
    }
    public static function staffCanAccess(User $user): bool {
        return $user->isActive() && ! in_array($user->access_role, [...User::MERCHANDISER_FIELD_ROLES, User::BRAND_PROMOTER_ROLE], true);
    }
    public function manageableBy(User $user): bool {
        return (int) $this->created_by === $user->id || $user->isCvoOrSuperAdmin() || $user->hasRole(['admin', 'super_admin']);
    }
}
