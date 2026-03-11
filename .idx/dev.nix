{ pkgs, ... }: {
  channel = "stable-24.05";

  packages = [];

  env = {};

  idx = {
    extensions = [
      "google.geminicodeassist"
      "Natizyskunk.sftp"
    ];

    previews = {
      enable = true;
      previews = {};
    };

    workspace = {
      onCreate = {
        setup-gitignore = ''
          echo ".vscode/sftp.json" >> .gitignore
          echo ".stats/" >> .gitignore
          echo ".backups/" >> .gitignore
        '';
      };
      onStart = {};
    };
  };
}
