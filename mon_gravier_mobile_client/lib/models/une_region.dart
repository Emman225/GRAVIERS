class UneRegion {
  int? id;
  String? nom;
  String? description;
  double? longitude;
  double? latitude;
  int? userId;
  String? createdAt;
  String? updatedAt;
  String? deletedAt;

  UneRegion(
      {this.id,
        this.nom,
        this.description,
        this.longitude,
        this.latitude,
        this.userId,
        this.createdAt,
        this.updatedAt,
        this.deletedAt});

  UneRegion.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    nom = json['nom'];
    description = json['description'];
    longitude = json['longitude'];
    latitude = json['latitude'];
    userId = json['user_id'];
    createdAt = json['created_at'];
    updatedAt = json['updated_at'];
    deletedAt = json['deleted_at'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = new Map<String, dynamic>();
    data['id'] = this.id;
    data['nom'] = this.nom;
    data['description'] = this.description;
    data['longitude'] = this.longitude;
    data['latitude'] = this.latitude;
    data['user_id'] = this.userId;
    data['created_at'] = this.createdAt;
    data['updated_at'] = this.updatedAt;
    data['deleted_at'] = this.deletedAt;
    return data;
  }
}
