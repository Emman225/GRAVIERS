import 'package:flutter/material.dart';
import 'package:mon_gravier_com_livreur/globale.dart';

class ProfilePic extends StatefulWidget {
  const ProfilePic({
    super.key,
  });

  @override
  State<ProfilePic> createState() => _ProfilePicState();
}

class _ProfilePicState extends State<ProfilePic> {
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 115,
      width: 115,
      child: ClipOval(
        child: Image.asset('assets/images/logo.png'),
        // child: (user.photo == null || user.photo.toString() == '' || user.photo.toString() == 'null')
        //     ? Image.asset('assets/images/livreur.png')
        //     : Image.network(user.photo.toString()),
      ),
      // Stack(
      //   fit: StackFit.expand,
      //   clipBehavior: Clip.none,
      //   children: [
      //     CircleAvatar(
      //       backgroundImage: (user.photo.toString() == '')
      //           ? NetworkImage('asstes/images/user.png')
      //           : NetworkImage('https://via.placeholder.com/150'),
      //       //AssetImage("assets/images/Profile Image.png"),
      //     ),
      //     Positioned(
      //       right: -16,
      //       bottom: 0,
      //       child: SizedBox(
      //         height: 46,
      //         width: 46,
      //         child: TextButton(
      //           style: TextButton.styleFrom(
      //             foregroundColor: Colors.white,
      //             shape: RoundedRectangleBorder(
      //               borderRadius: BorderRadius.circular(50),
      //               side: const BorderSide(color: Colors.white),
      //             ),
      //             backgroundColor: const Color(0xFFF5F6F9),
      //           ),
      //           onPressed: () {},
      //           child: SvgPicture.asset("assets/icons/Camera Icon.svg"),
      //         ),
      //       ),
      //     ),
      //   ],
      // ),
    );
  }
}
